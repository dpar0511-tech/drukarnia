# 12 — STRATEGIA TESTOWA

## 1. Framework

**Pest 2** (rekomendacja) lub **PHPUnit 11**. Wybór: Pest w nowych testach (czytelniejsze `it()` API), PHPUnit dla kompatybilności. `laravel/boost` reguły zalecają PHPUnit — jeśli ścisły compliance, trzymać się PHPUnit.

Uruchamianie:
```bash
php artisan test --compact                                # wszystkie
php artisan test --compact tests/Feature/Pricing          # moduł
php artisan test --compact --filter=testSheetsCeiling     # jeden
vendor/bin/pest --parallel                                # równolegle (Pest)
```

**Coverage target:** ≥80% na `app/Services/Pricing/` (krytyczny moduł). ≥60% reszta. Mierzone przez `php artisan test --coverage --min=80`.

---

## 2. Warstwy testów

### Unit (tests/Unit/)

Nie dotyka DB. Testuje pure functions i klasy bez IO:
- `tests/Unit/Pricing/Calculators/ImpositionCalculatorTest.php`
- `tests/Unit/Pricing/Calculators/MarginCalculatorTest.php`
- `tests/Unit/Pricing/Calculators/WasteCalculatorTest.php`
- `tests/Unit/Pricing/Calculators/RollSelectorCalculatorTest.php`
- `tests/Unit/Pricing/ValueObjects/*Test.php` (DTO immutability, validation)

### Feature (tests/Feature/)

Z DB (`RefreshDatabase` trait), pełny stack Laravela:
- `tests/Feature/Pricing/PipelineTest.php`
- `tests/Feature/Pricing/SimplePricingTest.php`
- `tests/Feature/Pricing/MultipageEngineTest.php`
- `tests/Feature/Pricing/ApiEndpointsTest.php`
- `tests/Feature/Orders/OrderCreationTest.php`
- `tests/Feature/Orders/OrderFsmTest.php`
- `tests/Feature/DAMS/TusUploadTest.php`
- `tests/Feature/DAMS/FileAccessPolicyTest.php`
- `tests/Feature/Finance/ProformaGeneratorTest.php`
- `tests/Feature/Integrations/SubiektAdapterTest.php` (z `Saloon::fake()`)
- `tests/Feature/Auth/LoginTest.php` (Breeze defaults)
- `tests/Feature/Rbac/PermissionMatrixTest.php`

### Browser (tests/Browser/)

**Laravel Dusk** — MVP-krytyczne flow:
- Login → Dashboard.
- Tworzenie zamówienia w Filamencie.
- Akceptacja projektu przez publiczny link.
- Upload pliku z tus.io.

Dusk uruchamiany z headless Chrome: `php artisan dusk`. Przed prod deployem — pełny przebieg.

---

## 3. 49 testów pricingu — mapowanie 1:1

Z `iwp_calc_generator-main/apps/pricing/tests/`:

### `CalculatorTest.php` (unit + feature mieszane)

```php
use App\Services\Pricing\Calculators\{ImpositionCalculator, MarginCalculator, WasteCalculator};

class CalculatorTest extends TestCase
{
    // ── Imposition
    public function test_imposition_a4_on_sra3_equals_2() {
        $r = ImpositionCalculator::calculate('450', '320', '297', '210', '2', '5');
        $this->assertSame(2, $r);
    }
    public function test_imposition_business_card_equals_20() {
        $r = ImpositionCalculator::calculate('450', '320', '90', '50', '2', '5');
        $this->assertSame(20, $r);
    }
    public function test_imposition_dl_equals_6() {
        $r = ImpositionCalculator::calculate('450', '320', '210', '99', '2', '5');
        $this->assertSame(6, $r);
    }
    public function test_imposition_small_bleed_large_margin_fits_less() { ... }

    // ── Margin
    public function test_margin_below_threshold() {
        $r = MarginCalculator::calculate('150.15', '100', '500', '30');
        $this->assertSame('150.15', $r);    // 150.15 × 100% = 150.15
    }
    public function test_margin_above_threshold_two_tier() {
        $r = MarginCalculator::calculate('700', '100', '500', '30');
        $this->assertSame('560.00', $r);    // 500×1.0 + 200×0.30 = 560
    }
    public function test_margin_zero_cost_returns_zero() {
        $this->assertSame('0', MarginCalculator::calculate('0', '100', '500', '30'));
    }

    // ── Waste
    public function test_sheet_waste_pct_plus_fixed() {
        $this->assertSame(273, WasteCalculator::applySheet(250, '5', 10));  // ⌈262.5⌉+10
    }
    public function test_meter_waste_pct_plus_fixed_rounded_up_to_01() {
        $r = WasteCalculator::applyMeter('10', '5', '0');
        $this->assertSame('10.5', $r);     // 10×1.05=10.5 → już 0.1
    }
}
```

### `SimplePricingTest.php`

```php
class SimplePricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PricingDemoSeeder::class);
        $this->svc = app(SimplePricingService::class);
        $this->mugs = Product::where('slug', 'kubki-firmowe')->first();
    }

    public function test_no_discount_when_quantity_below_start() {
        $r = $this->svc->calculate($this->mugs, quantity: 1, selectedOptionIds: []);
        $this->assertFalse($r->discountApplied);
        $this->assertSame('25.00', $r->baseUnitAfterDiscount);
    }
    public function test_discount_active_but_not_capped_at_100() { ... }
    public function test_discount_caps_at_minimum_for_5000() {
        $r = $this->svc->calculate($this->mugs, 5000, []);
        $this->assertTrue($r->discountHitMinimum);
        $this->assertSame('12.00', $r->baseUnitAfterDiscount);
    }
    public function test_options_added_outside_discount() { ... }
    public function test_options_raise_above_minimum() { ... }
    public function test_missing_simple_product_discount_throws() {
        $p = Product::factory()->create(['product_type' => 'simple']);
        $this->expectException(PriceCalculationException::class);
        $this->svc->calculate($p, 10, []);
    }
}
```

### `PipelineTest.php`

```php
class PipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_flyers_a4_500_positive_price_in_range() {
        $r = $this->pipeline->calculate($this->flyers, $this->configFor(qty: 500));
        $this->assertTrue(BigDecimal::of($r->finalPriceNet)->isPositive());
        // per-piece w zakresie 0.40 - 1.00 PLN
        $perPiece = BigDecimal::of($r->finalPriceNet)->dividedBy(500, 4, RoundingMode::HALF_UP);
        $this->assertTrue($perPiece->isGreaterThan('0.40') && $perPiece->isLessThan('1.00'));
    }
    public function test_larger_quantity_more_sheets_higher_price() { ... }
    public function test_double_sided_more_expensive_than_single_sided() { ... }
    public function test_express_equals_standard_times_130() { ... }
    public function test_folia_mat_parameter_raises_price() { ... }
    public function test_catalog_4_plus_8_project_price_170() { ... }
    public function test_more_pages_larger_project_price() { ... }
    public function test_order_project_false_zero_project_price() { ... }
    public function test_mugs_discount_active_not_capped_at_100() { ... }
    public function test_mugs_cap_minimum_at_5000() { ... }
    public function test_mugs_option_raises_base() { ... }
    public function test_mugs_option_outside_minimum_discount() { ... }
}
```

### `MultipageEngineTest.php`

```php
class MultipageEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_sections_separate_materials() { ... }
    public function test_sheets_per_piece_equals_ceil_pages_over_2() { ... }
    public function test_zadruk_common_sums_both_sections() { ... }
    public function test_missing_page_config_throws() { ... }
    public function test_missing_cover_material_throws() { ... }
    public function test_missing_interior_material_throws() { ... }
    public function test_zero_pages_section_skipped() { ... }
}
```

---

## 4. Factories i seeders

### `database/factories/PricingFactory.php`

```php
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->slug,
            'product_type' => 'calculated',
            'engine_type' => 'sheet',
            'is_active' => true,
        ];
    }

    public function simple(): self { return $this->state(['product_type' => 'simple', 'engine_type' => null]); }
    public function multipage(): self { return $this->state(['engine_type' => 'multipage', 'multipage_type' => 'skladany', 'has_multipage' => true]); }
    public function linearMeter(): self { return $this->state(['engine_type' => 'linear_meter']); }
    public function withPatterns(int $default = 1): self { return $this->state(['has_patterns' => true, 'default_pattern_count' => $default]); }
    public function withProject(): self { return $this->state(['has_project' => true]); }
}
```

Analogicznie dla `MaterialFactory`, `ParameterFactory`, `ZadrukOptionFactory` itd.

### Seeder demo

`PricingDemoSeeder` — port `seed_demo.py` z Django:
```bash
php artisan db:seed --class=PricingDemoSeeder
```

Zapewnia determinizm testów (te same ID, te same wartości) + demo dla dev/QA.

---

## 5. Testy integracji (adapters)

### Subiekt adapter

```php
class SubiektAdapterTest extends TestCase
{
    public function test_issue_proforma_returns_document_number() {
        Saloon::fake([
            CreateSubiektProformaRequest::class => MockResponse::make(['number' => 'PRO/2026/0001', 'id' => 'xyz'], 201),
        ]);

        $adapter = app(SubiektAdapter::class);
        $doc = $adapter->issueProforma(Zamowienie::factory()->create());

        $this->assertSame('PRO/2026/0001', $doc->number_in_subiekt);
        Saloon::assertSent(CreateSubiektProformaRequest::class);
    }

    public function test_retry_on_500_then_success() {
        Saloon::fake([
            fn () => MockResponse::make([], 500),
            fn () => MockResponse::make([], 500),
            fn () => MockResponse::make(['number' => 'OK'], 201),
        ]);
        // assert po 3 próbach dostajemy OK
    }
}
```

### Przelewy24 webhook

```php
public function test_payment_webhook_updates_document() {
    $doc = DokumentFinansowy::factory()->create(['status' => 'issued']);

    $payload = [
        'sessionId' => 'sess-123',
        'orderId'   => $doc->id,
        'amount'    => $doc->amount_brutto * 100,
        'currency'  => 'PLN',
    ];
    $payload['sign'] = md5("{$payload['sessionId']}|{$payload['orderId']}|{$payload['amount']}|{$payload['currency']}|".config('services.przelewy24.crc_key'));

    $this->post('/webhooks/przelewy24', $payload)
        ->assertNoContent();

    $this->assertDatabaseHas('dokumenty_finansowe', ['id' => $doc->id, 'status' => 'paid']);
    $this->assertDatabaseHas('webhook_events', ['provider' => 'przelewy24']);
}

public function test_webhook_rejected_with_invalid_crc() {
    $this->post('/webhooks/przelewy24', ['sign' => 'wrong'])
        ->assertUnauthorized();
}

public function test_webhook_idempotent_on_duplicate() {
    // wyślij 2× ten sam payload → tylko 1 update
}
```

---

## 6. Testy RBAC

```php
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @dataProvider provideRoleAccessMatrix */
    public function test_role_permission_matrix(string $role, string $route, int $expectedStatus) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get($route)
            ->assertStatus($expectedStatus);
    }

    public static function provideRoleAccessMatrix(): array
    {
        return [
            ['admin',       '/admin',           200],
            ['menedzer',    '/admin',           403],
            ['operator',    '/orders',          403],  // operator nie widzi zamówień
            ['klient',      '/portal/orders',   200],
            ['klient',      '/admin',           403],
            ['ksiegowosc',  '/finance/documents', 200],
            ['ksiegowosc',  '/production/kanban', 403],  // księgowość nie dotyka produkcji
            // … pełna macierz 6 ról × 20 route'ów
        ];
    }
}
```

---

## 7. Testy FSM

```php
class OrderFsmTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_transition_new_to_pricing() {
        $z = Zamowienie::factory()->create(['status' => 'NOWE']);
        $z->status->transitionTo(Pricing::class);
        $this->assertSame('WYCENA', (string) $z->fresh()->status);
    }

    public function test_illegal_transition_throws() {
        $z = Zamowienie::factory()->create(['status' => 'NOWE']);
        $this->expectException(TransitionNotFound::class);
        $z->status->transitionTo(InProduction::class);   // NOWE → W_PRODUKCJI nielegalne
    }

    /** @dataProvider provideIllegalTransitions */
    public function test_all_illegal_transitions_throw(string $from, string $to) {
        // wygeneruj z matrix wszystkie (from, to) które NIE są w config()->allowTransition
    }
}
```

---

## 8. Testy DAMS

```php
class TusUploadTest extends TestCase
{
    public function test_finalize_moves_file_and_creates_record() {
        $tempPath = storage_path('app/temp/tus/abc-def');
        file_put_contents($tempPath, 'fake pdf content');

        $p = app(FileUploader::class)->finalize($tempPath, 'test.pdf', orderId: 1);

        $this->assertSame('application/pdf', $p->mime_type); // po sniff
        $this->assertFileDoesNotExist($tempPath);
        $this->assertFileExists(Storage::disk('local_private')->path($p->local_path));
    }

    public function test_file_access_denied_for_wrong_client() {
        $order1Client = User::factory()->create()->assignRole('klient');
        $order2Client = User::factory()->create()->assignRole('klient');
        $plik = Plik::factory()->forOrderOf($order1Client)->create();

        $this->actingAs($order2Client)
            ->get("/files/{$plik->uuid}")
            ->assertForbidden();
    }

}
```

---

## 9. Testy wydajności

### Pricing

```php
public function test_pricing_pipeline_under_500ms() {
    $start = microtime(true);
    $this->pipeline->calculate($this->flyers, $this->configFor(500));
    $elapsed = (microtime(true) - $start) * 1000;
    $this->assertLessThan(500, $elapsed, 'Pipeline > 500ms');
}

public function test_pricing_grid_under_1500ms() {
    $start = microtime(true);
    $this->post('/api/pricing/grid', [...]);
    $this->assertLessThan(1500, (microtime(true) - $start) * 1000);
}
```

### Larger
- `k6` scripts w `tests/Performance/*.js` dla load testingu (Faza 3).

---

## 10. CI matrix

`.github/workflows/ci.yml` uruchamia:
1. `vendor/bin/pint --test --format agent` — lint.
2. `php artisan test --compact --coverage --min=80 tests/Feature/Pricing tests/Unit/Pricing` — pricing coverage.
3. `php artisan test --compact` — wszystkie testy.
4. `npm run build` — frontend build nie psuje.
5. `composer audit` + `npm audit` — security.

Merge na `main` zablokowany, jeśli którykolwiek fail.

---

## 11. Testy ad-hoc — zasady

- **Nie używać `tinker` do testowania logiki biznesowej** — zawsze `test`.
- **Verification scripts zakazane** — jeśli feature wymaga weryfikacji, pisz test.
- **Testy wykonują się na świeżej DB** (`RefreshDatabase`) — nigdy na dev DB.
- **Żadne testy nie zostawiają residuów** — po każdym teście DB wraca do baseline.
- **Factory states** zamiast ręcznych insertów w testach.
- **Mocki integracji** — Saloon, Http, Process.
- **Data providers** dla tablic scenariuszy (FSM, RBAC matrix).

---

## 12. Organizacja testów pricingu

```
tests/
├── Feature/
│   └── Pricing/
│       ├── PipelineTest.php                 ← 12 scenariuszy
│       ├── SimplePricingTest.php             ← 6 scenariuszy
│       ├── MultipageEngineTest.php           ← 7 scenariuszy
│       ├── ApiEndpointsTest.php              ← /api/pricing/calculate itd.
│       ├── ExclusionResolverTest.php         ← reguły wykluczeń
│       └── SettingResolverTest.php           ← kaskadowe dziedziczenie
│
└── Unit/
    └── Pricing/
        ├── Calculators/
        │   ├── ImpositionCalculatorTest.php   ← 4+ scenariusze
        │   ├── MarginCalculatorTest.php       ← 3 scenariusze
        │   ├── WasteCalculatorTest.php        ← 2 scenariusze (sheet + meter)
        │   └── RollSelectorCalculatorTest.php
        └── ValueObjects/
            ├── CalculationConfigTest.php
            └── PricingResultTest.php
```

**Suma:** minimum 49 testów = parity z `iwp_calc_generator-main`. W praktyce ~60+ po dodaniu testów specyficznych dla Laravel (API endpoints, DI binding, event dispatch).
