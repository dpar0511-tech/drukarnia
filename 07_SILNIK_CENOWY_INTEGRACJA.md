# 07 — SILNIK CENOWY: PEŁNA INTEGRACJA W ERP

> **Status:** źródło prawdy silnika cenowego w nowym ERP.
> **Pochodzenie:** `_nowy_SILNIK_CENOWY/01..08` (Django 6) → przeniesienie 1:1 do Laravel 11.
> **Krytyczna zasada:** **żaden detal obliczeń nie może zostać utracony.**

---

## 1. Rola i zastępstwo

Silnik cenowy to **jedyne źródło prawdy cen** w ERP. Zastępuje w całości dawny `MODULE 4 — PRICING & LOYALTY ENGINE` z `_ERP_nowy/MASTER_PLAN.md`:

| Z `MASTER_PLAN.md` | Status | Uzasadnienie |
|--------------------|--------|--------------|
| `RegulaCenowa` | **USUNIĘTE** | Ceny kalkulowane dynamicznie przez `PricingPipeline` |
| `IndywidualyCennik` | Opcjonalne (Faza 2+) | Warstwa nad silnikiem per klient (overrides), nie główny mechanizm |
| `RabatDefinicja` | **USUNIĘTE** | Dla produktów prostych: `SimpleProductDiscount`. Dla kalkulowanych: marża dwupoziomowa |
| `KalkulacjaCeny` | **ZACHOWANE** | Jako snapshot wyniku (tabela `kalkulacje_ceny`, patrz 04) |
| `KosztWlasny` | **USUNIĘTE** | `total_cost` + `engine_result` z pipeline = koszty własne |

**Zachowane z `MASTER_PLAN.md`:** event `PriceCalculated`, integracja z Finance (Proforma po wycenie), workflow statusów FSM.

---

## 2. Typy produktów

```php
// app/Enums/ProductType.php
enum ProductType: string {
    case CALCULATED = 'calculated';   // 12-krokowy pipeline
    case SIMPLE = 'simple';           // wzór bazowy + rabat wykładniczy
}

// app/Enums/EngineType.php
enum EngineType: string {
    case SHEET = 'sheet';
    case LINEAR_METER = 'linear_meter';
    case MULTIPAGE = 'multipage';
}
```

**Walidacje (`Product::validated()` w Form Request / observer):**
- `product_type = CALCULATED` → `engine_type` NOT NULL.
- `product_type = SIMPLE` → `engine_type = NULL`, `has_multipage = false`, wymagany `SimpleProductDiscount` (1:1).
- `engine_type = MULTIPAGE` → wymaga `multipage_type` NOT NULL i automatycznie ustawia `has_multipage = true`.

---

## 3. 12-krokowy PricingPipeline (dla `CALCULATED`)

**Plik:** `app/Services/Pricing/PricingPipeline.php`

### 3.1 Sygnatura i kontrakt

```php
namespace App\Services\Pricing;

use App\Models\Products\Product;
use App\Services\Pricing\ValueObjects\{CalculationConfig, PricingResult};

final class PricingPipeline
{
    public function __construct(
        private EngineResolver $engines,
        private SettingResolver $settings,
        private ProjectPageResolver $projectPages,
    ) {}

    public function calculate(Product $product, CalculationConfig $config): PricingResult;
}
```

### 3.2 Wejście: CalculationConfig (readonly class)

```php
// app/Services/Pricing/ValueObjects/CalculationConfig.php
final readonly class CalculationConfig
{
    public function __construct(
        public string $formatWidthMm,              // BigDecimal as string
        public string $formatHeightMm,
        public string $unfoldedWidthMm,
        public string $unfoldedHeightMm,
        public ?int $formatId = null,
        public ?int $mediaFormatId = null,
        public ?int $coverMaterialId = null,
        public ?int $interiorMaterialId = null,
        public ?int $defaultMaterialId = null,
        public ?int $zadrukOptionId = null,
        public ?int $pageCount = null,
        public int $quantity = 1,
        public int $patternCount = 1,
        public bool $orderProject = false,
        public ?int $timeOptionId = null,
        public ?int $pageConfigId = null,
        public array $selectedParameterOptionIds = [],       // PK `product_parameter_option`, NIE `parameter_option`!
        public array $selectedSimpleParameterOptionIds = [],
    ) {}
}
```

**Uwaga (z pliku 02 źródłowego):** `selectedParameterOptionIds` to lista PK tabeli **`product_parameter_option`** (pivot z rolą!), nie `parameter_option`. Pipeline filtruje po `product` i `whereIn('id', $ids)`.

### 3.3 Wyjście: PricingResult

```php
final readonly class PricingResult
{
    public function __construct(
        public EngineResult $engineResult,
        public string $extrasCost = '0',            // BigDecimal
        public string $productExtraCost = '0',
        public string $totalCost = '0',
        public string $marginAmount = '0',
        public string $basePrice = '0',
        public string $extrasPrice = '0',
        public string $productExtraPrice = '0',
        public string $projectPrice = '0',
        public string $patternMultipliedPrice = '0',
        public string $timeMultiplier = '1',
        public string $finalPriceNet = '0',         // 🔥 wynik
        public array $details = [],                 // ['units_consumed', 'unit_type', 'quantity', 'pages', 'order_project']
    ) {}
}
```

### 3.4 Kroki 1–12 (literalna lista z źródła)

#### KROK 1 — Silnik obliczeniowy

```php
$engine = $this->engines->get($product->engine_type);  // ValueError jeśli null
$engineResult = $engine->calculate($product, $config);
```

`EngineResult`:
```php
final readonly class EngineResult
{
    public function __construct(
        public string $unitType,         // "sheet" | "meter"
        public string $unitsConsumed,    // BigDecimal
        public int $imposition,
        public string $materialCost,
        public string $printCost,
        public array $details = [],      // per engine: sheet_size, piece_size, sheets_base, sheets_final, sections, …
    ) {}
}
```

Szczegóły każdego silnika → sekcja 4.

#### KROKI 2–3 — Koszty parametrów i produktowe (PRE-MARGIN)

Pobiera opcje z `product_parameter_option` i segreguje per rola:

```php
$ppos = ProductParameterOption::where('product_id', $product->id)
    ->whereIn('id', $config->selectedParameterOptionIds)
    ->with('parameterOption')
    ->get();

$commonOptions   = $ppos->where('role', PartRole::COMMON)->pluck('parameterOption');
$coverOptions    = $ppos->where('role', PartRole::COVER)->pluck('parameterOption');
$interiorOptions = $ppos->where('role', PartRole::INTERIOR)->pluck('parameterOption');

$sections = $engineResult->details['sections'] ?? [];
$coverUnits    = $sections['cover']['sheets_final'] ?? 0;
$interiorUnits = $sections['interior']['sheets_final'] ?? 0;

$extrasCost = BigDecimal::of('0')
    ->plus($this->sumCostFields($commonOptions,   $engineResult->unitType, $engineResult->unitsConsumed, $config->quantity))
    ->plus($this->sumCostFields($coverOptions,    'sheet', $coverUnits, $config->quantity))
    ->plus($this->sumCostFields($interiorOptions, 'sheet', $interiorUnits, $config->quantity));

$productExtraCost = $this->sumCostFields([$product], $engineResult->unitType, $engineResult->unitsConsumed, $config->quantity);
```

**Metoda `sumCostFields` (literalnie z pliku 02):**
```php
private function sumCostFields(iterable $items, string $unitType, string|int $units, int $qty): string
{
    $total = BigDecimal::of('0');
    foreach ($items as $item) {
        $total = $total->plus(BigDecimal::of($item->cost_total ?? '0'));
        if ($unitType === 'sheet') {
            $total = $total->plus(BigDecimal::of($item->cost_per_sheet ?? '0')->multipliedBy($units));
        } elseif ($unitType === 'meter') {
            $total = $total->plus(BigDecimal::of($item->cost_per_meter ?? '0')->multipliedBy($units));
        }
        $total = $total->plus(BigDecimal::of($item->cost_per_piece ?? '0')->multipliedBy($qty));
    }
    return (string) $total;
}
```

**Reguła roli (krytyczna!):**
- `COMMON` → mnożone przez `engineResult.unitsConsumed`, z typem `engineResult.unitType`.
- `COVER` → mnożone przez `sections.cover.sheets_final`, typ `"sheet"`.
- `INTERIOR` → mnożone przez `sections.interior.sheets_final`, typ `"sheet"`.
- Dla silników bez wielostronności: `coverUnits = 0, interiorUnits = 0` → tylko COMMON.

#### KROK 4 — Suma kosztów

```php
$totalCost = BigDecimal::of($engineResult->materialCost)
    ->plus($engineResult->printCost)
    ->plus($extrasCost)
    ->plus($productExtraCost);
```

#### KROK 5 — Marża dwupoziomowa

```php
$baseP   = $this->settings->resolve($product, 'margin_base_pct');     // np. '100'
$thresh  = $this->settings->resolve($product, 'margin_threshold');    // '500.00'
$aboveP  = $this->settings->resolve($product, 'margin_above_pct');    // '30'

$marginAmount = MarginCalculator::calculate($totalCost, $baseP, $thresh, $aboveP);
```

`MarginCalculator` (implementacja w sekcji 5):

```php
public static function calculate(string $totalCost, string $basePct, string $threshold, string $abovePct): string
{
    $cost = BigDecimal::of($totalCost);
    if ($cost->isLessThanOrEqualTo('0')) return '0';

    $below = BigDecimal::min($cost, BigDecimal::of($threshold));
    $above = BigDecimal::max(BigDecimal::of('0'), $cost->minus(BigDecimal::of($threshold)));

    return (string) $below->multipliedBy($basePct)->dividedBy('100', 2, RoundingMode::HALF_UP)
        ->plus($above->multipliedBy($abovePct)->dividedBy('100', 2, RoundingMode::HALF_UP));
}
```

**Przykład (z źródła):** koszt 700 PLN, próg 500, baza 100%, ponad 30% → `(500×1.0) + (200×0.30) = 560.00`.

#### KROK 6 — Cena bazowa

```php
$basePrice = $totalCost->plus($marginAmount);
```

#### KROK 7 — Mnożnik wzorów

Aktywny gdy `product.has_patterns && config.patternCount > 1`:

```php
if ($product->has_patterns && $config->patternCount > 1) {
    $mult = $this->settings->resolve($product, 'pattern_multiplier');  // np. 0.70
    $factor = BigDecimal::of('1')->plus(
        BigDecimal::of($config->patternCount - 1)->multipliedBy($mult)
    );
    $patternMultipliedPrice = $basePrice->multipliedBy($factor);
} else {
    $patternMultipliedPrice = $basePrice;
}
```

**Wzór (cytat z źródła):** `factor = 1 + (count − 1) × multiplier`. Dla 3 wzorów i 0.70: `1 + 2×0.70 = 2.40` → cena ×2.40.

#### KROKI 8–9 — Ceny parametrów i produktowe (POST-MARGIN)

Analogicznie do 2–3, ale z `price_*` (nie `cost_*`) i sumowane **po** marży i mnożniku wzorów:

```php
$extrasPrice = BigDecimal::of('0')
    ->plus($this->sumPriceFields($commonOptions,   $engineResult->unitType, $engineResult->unitsConsumed, $config->quantity))
    ->plus($this->sumPriceFields($coverOptions,    'sheet', $coverUnits, $config->quantity))
    ->plus($this->sumPriceFields($interiorOptions, 'sheet', $interiorUnits, $config->quantity));

$productExtraPrice = $this->sumPriceFields([$product], $engineResult->unitType, $engineResult->unitsConsumed, $config->quantity);

$priceAfterExtras = $patternMultipliedPrice->plus($extrasPrice)->plus($productExtraPrice);
```

`sumPriceFields` identyczne jak `sumCostFields`, ale z polami `price_*`.

**Krytyczna różnica cost vs price:**
- `cost_*` → do `total_cost` (krok 4) → **naliczana marża**.
- `price_*` → dodawane po marży i po wzorach → **marża nie wpływa**.

#### KROK 10 — Cena projektu (POST-MARGIN)

Aktywny gdy `product.has_project && config.orderProject`:

```php
$pages    = $this->projectPages->resolve($product, $config);
$base     = $this->settings->resolve($product, 'project_base_price');   // '50.00'
$perPage  = $this->settings->resolve($product, 'project_price_per_page'); // '10.00'
$projectPrice = BigDecimal::of($base)->plus(BigDecimal::of($perPage)->multipliedBy($pages));
$priceAfterExtras = $priceAfterExtras->plus($projectPrice);
```

**`ProjectPageResolver::resolve(Product, CalculationConfig): int`** — priorytet (z pliku 02):
1. Wielostronność: jeśli `has_multipage && pageConfigId` → `PageConfig.total_pages` (= `cover_pages + interior_pages`).
2. Zadruk: jeśli `zadrukOptionId && zadruk.project_page_count > 0` → `project_page_count`.
3. Manual: `config.pageCount > 0`.
4. Fallback: `1`.

**Przykład:** Katalog Szyty 4+8 → pages=12 → `50 + 10×12 = 170.00`.

#### KROK 11 — Mnożnik czasu realizacji

`PricingPipeline::resolveTimeMultiplier(Product, CalculationConfig): string` — algorytm z pliku 02:

```php
private function resolveTimeMultiplier(Product $product, CalculationConfig $config): string
{
    if ($config->timeOptionId !== null) {
        $pto = ProductTimeOption::where('product_id', $product->id)
            ->where('time_option_id', $config->timeOptionId)->first();
        if ($pto) return (string) $pto->effective_multiplier;

        $to = TimeOption::find($config->timeOptionId);
        if ($to) return (string) $to->multiplier;
        return '1';
    }

    // Brak wybranego — szukamy default
    $pto = ProductTimeOption::where('product_id', $product->id)
        ->where('is_default', true)->first();
    if ($pto) return (string) $pto->effective_multiplier;

    $to = TimeOption::where('is_default', true)->first();
    if ($to) return (string) $to->multiplier;
    return '1';
}
```

**`ProductTimeOption::effective_multiplier`** — accessor:
```php
public function getEffectiveMultiplierAttribute(): string
{
    return (string) ($this->multiplier_override ?? $this->timeOption->multiplier);
}
```

**Przykład:** Standard 1.00, Express 1.30.

#### KROK 12 — Cena końcowa netto

```php
$finalPriceNet = $priceAfterExtras->multipliedBy($timeMultiplier);
```

**To jest cena netto za cały nakład**. W konfiguratorze dodatkowo:

```php
$vatRate = '0.23';
$vat     = $finalPriceNet->multipliedBy($vatRate);
$gross   = $finalPriceNet->plus($vat);
$perPieceNet   = $finalPriceNet->dividedBy($quantity, 4, RoundingMode::HALF_UP);
$perPieceGross = $gross->dividedBy($quantity, 4, RoundingMode::HALF_UP);
```

---

## 4. Silniki obliczeniowe

**Plik bazowy:** `app/Services/Pricing/Engines/CalculationEngine.php`

```php
interface CalculationEngine
{
    public function engineType(): EngineType;
    public function calculate(Product $product, CalculationConfig $config): EngineResult;
}
```

**Rejestr:** `app/Services/Pricing/Engines/EngineResolver.php` — binduje w `AppServiceProvider::register()` jako singleton, wstrzykuje wszystkie 3 silniki przez tagged container lub array DI.

```php
$this->app->singleton(EngineResolver::class, fn ($app) => new EngineResolver([
    EngineType::SHEET->value        => $app->make(SheetEngine::class),
    EngineType::LINEAR_METER->value => $app->make(LinearMeterEngine::class),
    EngineType::MULTIPAGE->value    => $app->make(MultipageEngine::class),
]));
```

`EngineResolver::get(string $type): CalculationEngine` rzuca `\InvalidArgumentException` gdy typ nieznany.

### 4.1 SheetEngine — druk arkuszowy

Dla ulotek, wizytówek, plakatów. `engine_type = sheet`.

**Algorytm (krok po kroku, literalnie z pliku 03):**

1. Resolwowanie ustawień:
   ```
   bleed        = resolveSetting(product, 'bleed_mm')       // 2 mm
   sheet_margin = resolveSetting(product, 'sheet_margin_mm') // 5 mm
   waste_pct    = resolveSetting(product, 'waste_pct')       // 5%
   waste_fixed  = resolveSetting(product, 'waste_fixed')     // 10
   ```
2. Pobierz `ZadrukOption` po `zadrukOptionId` (opcjonalne).
3. Pobierz materiał + rozmiar (`_pickSize`):
   ```
   material = Material::find(defaultMaterialId)
   size     = pickSize(material, mediaFormatId)
   sheet_w  = size.mediaFormat.width_mm
   sheet_h  = size.mediaFormat.height_mm
   cost_per_unit = size.cost_per_unit
   ```
4. Impozycja → `ImpositionCalculator::calculate(sheet_w, sheet_h, unfolded_w, unfolded_h, bleed, sheet_margin)` (sekcja 5.1).
   - `imposition <= 0` → **throw** `PriceCalculationException('Produkt nie mieści się na arkuszu')`.
5. Arkusze:
   ```
   sheets_base  = (int) ceil(quantity / imposition)
   sheets_final = WasteCalculator::applySheet(sheets_base, waste_pct, waste_fixed)
   ```
6. Koszty:
   ```
   material_cost = sheets_final × cost_per_unit
   print_cost    = zadruk.cost_total
                 + zadruk.cost_per_sheet × sheets_final
                 + zadruk.cost_per_piece × quantity
                 // cost_per_meter ignorowane w SheetEngine
   ```
7. Return `EngineResult(unitType='sheet', unitsConsumed=sheets_final, imposition, materialCost, printCost, details={sheet_size, piece_size, sheets_base, sheets_final, mode:'simple'})`.

#### `_pickSize` (z pliku 03)

```php
private function pickSize(Material $material, ?int $mediaFormatId): MaterialSize
{
    $q = $material->sizes()->with('mediaFormat');
    if ($mediaFormatId !== null) {
        $size = (clone $q)->where('media_format_id', $mediaFormatId)->first();
        if ($size) return $size;
    }
    $size = $q->first();
    if (!$size) {
        throw new PriceCalculationException("Materiał '{$material->name}' nie ma zdefiniowanego rozmiaru (MaterialSize).");
    }
    return $size;
}
```

**Uwaga:** `LinearMeterEngine` **importuje** tę metodę (trait lub helper class). `MultipageEngine` ma własną kopię (duplikacja — zachowane z źródła).

### 4.2 LinearMeterEngine — druk z rolki

Dla bannerów, naklejek, winyli. `engine_type = linear_meter`.

**Algorytm:**

1. `bleed`, `waste_pct = waste_meter_pct`, `waste_fixed = waste_meter_fixed` (**inne odpady niż Sheet!**).
2. Material + size przez `pickSize`. `roll_width_mm = size.mediaFormat.width_mm`, `cost_per_meter = size.cost_per_unit`.
3. Kolumny na rolce:
   ```
   piece_w = unfolded_width_mm + 2 × bleed
   piece_h = unfolded_height_mm + 2 × bleed
   cols    = (int) floor(roll_width_mm / piece_w)       // integer floor!
   if (cols <= 0) throw "produkt nie mieści się na rolce"
   ```
4. Długość:
   ```
   rows_needed = (int) ceil(quantity / cols)
   length_mm   = rows_needed × piece_h
   meters_base = ceil(length_mm / 1000 → do 0.1 m)
   ```
   PHP:
   ```php
   $metersBase = BigDecimal::of($lengthMm)
       ->dividedBy('1000', 10, RoundingMode::UP)
       ->toScale(1, RoundingMode::UP);
   ```
5. Odpad metrowy → `WasteCalculator::applyMeter(meters_base, waste_pct, waste_fixed)`.
6. Koszty:
   ```
   material_cost = meters_final × cost_per_meter
   print_cost    = zadruk.cost_total
                 + zadruk.cost_per_meter × meters_final    // PER METER!
                 + zadruk.cost_per_piece × quantity
   ```
7. Return `EngineResult(unitType='meter', unitsConsumed=metersFinal, imposition=cols, materialCost, printCost, details={roll_width_mm, columns, rows, length_mm, meters_base, meters_final})`.

**Różnice vs SheetEngine (z pliku 03):**
- Bleed dodawany do wymiarów w silniku (nie w impozycji).
- Odpad metrowy.
- `cost_per_meter` z `ZadrukOption` (zamiast `cost_per_sheet`).
- `imposition` = kolumny na rolce.
- `meters_base` zaokrąglone w górę do 0.1 m **przed** odpadem.

### 4.3 MultipageEngine — druk wielostronny

Dla katalogów szytych/klejonych/spiralowanych. `engine_type = multipage`.

**Wymagania (wszystkie wymagają wejścia, throw jeśli brak):**
- `config.pageConfigId`
- `config.coverMaterialId`
- `config.interiorMaterialId`
- Zadruk **wspólny** dla obu sekcji.

**Algorytm:**

1. `$pageCfg = PageConfig::find($config->pageConfigId)` — `cover_pages`, `interior_pages`.
2. `$coverMaterial`, `$interiorMaterial`.
3. Ustawienia: `bleed`, `sheet_margin`, `waste_pct`, `waste_fixed` (arkuszowe, jak Sheet).
4. Dla każdej sekcji (`cover`, `interior`):
   ```
   // Zasada: 1 arkusz = 2 strony (druk dwustronny)
   if pages <= 0: section = { skipped: true }  → skip
   
   size             = pickSize(material, mediaFormatId)
   sheets_per_piece = (int) ceil(pages / 2)                              // 🔥 krytyczne
   imposition       = ImpositionCalculator::calculate(sheet_w, sheet_h, unfolded_w, unfolded_h, bleed, sheet_margin)
   if imposition <= 0: throw
   sheets_base      = (int) ceil(quantity × sheets_per_piece / imposition)  // 🔥 wzór kluczowy
   sheets_final     = WasteCalculator::applySheet(sheets_base, waste_pct, waste_fixed)
   section_cost     = sheets_final × size.cost_per_unit
   total_material_cost += section_cost
   total_sheets        += sheets_final
   sections[role] = { pages, sheets_per_piece, imposition, sheets_base, sheets_final, material, sheet_size }
   ```
5. Zadruk (wspólny, od sumy):
   ```
   print_cost = zadruk.cost_total
              + zadruk.cost_per_sheet × total_sheets
              + zadruk.cost_per_piece × quantity
   ```
6. Return `EngineResult(unitType='sheet', unitsConsumed=total_sheets, imposition=1, materialCost=total_material_cost, printCost, details={ mode:'multipage', cover_pages, interior_pages, sections: {cover: {...}, interior: {...}} })`.

**Krytyczne szczegóły (z pliku 03):**
- 2 osobne materiały (okładka grubszy, wnętrze cieńszy).
- `sheets_per_piece = ceil(pages/2)` — **nie zapomnieć!**
- Zadruk naliczany od sumy arkuszy obu sekcji.
- `sections.{cover,interior}.sheets_final` używane w krokach 2–3 i 8–9 pipeline'u do mnożenia parametrów z rolą COVER/INTERIOR.
- `MultipageType` (`skladany` / `rozdzielony`) to **tylko etykieta UI** — silnik liczy **identycznie** dla obu typów.
- Sekcja z `pages = 0` → pomijana z `skipped: true`.

---

## 5. Kalkulatory pomocnicze

### 5.1 ImpositionCalculator

**Plik:** `app/Services/Pricing/Calculators/ImpositionCalculator.php`

```php
final class ImpositionCalculator
{
    public static function calculate(
        string $sheetWmm, string $sheetHmm,
        string $pieceWmm, string $pieceHmm,
        string $bleedMm = '2', string $sheetMarginMm = '5'
    ): int {
        $usableW = BigDecimal::of($sheetWmm)->minus(BigDecimal::of('2')->multipliedBy($sheetMarginMm));
        $usableH = BigDecimal::of($sheetHmm)->minus(BigDecimal::of('2')->multipliedBy($sheetMarginMm));
        $pW = BigDecimal::of($pieceWmm)->plus(BigDecimal::of('2')->multipliedBy($bleedMm));
        $pH = BigDecimal::of($pieceHmm)->plus(BigDecimal::of('2')->multipliedBy($bleedMm));

        $fit = function ($uw, $uh, $pw, $ph) {
            if ($pw->isLessThanOrEqualTo('0') || $ph->isLessThanOrEqualTo('0')) return 0;
            $cols = (int) $uw->dividedBy($pw, 0, RoundingMode::DOWN)->toInt();
            $rows = (int) $uh->dividedBy($ph, 0, RoundingMode::DOWN)->toInt();
            return max(0, $cols) * max(0, $rows);
        };

        $fitA = $fit($usableW, $usableH, $pW, $pH);     // normalna
        $fitB = $fit($usableW, $usableH, $pH, $pW);     // obrócona 90°
        return max($fitA, $fitB);
    }
}
```

**Krytyczne:** dzielenie `cols/rows` — **floor** (obcinanie), nie zaokrąglanie. `RoundingMode::DOWN`.

**Przykłady (z pliku 03):**
| Arkusz | Produkt | Wynik |
|--------|---------|-------|
| SRA3 450×320 | A4 297×210 bleed 2 mm, margin 5 mm | **2** (orientacja B: ⌊440/214⌋×⌊310/301⌋=2×1) |
| SRA3 | Wizytówka 90×50 | **20** (⌊440/94⌋×⌊310/54⌋=4×5) |
| SRA3 | DL 210×99 | **6** (⌊440/214⌋×⌊310/103⌋=2×3) |

### 5.2 MarginCalculator

(Już pokazany w 3.4 krok 5.)

### 5.3 WasteCalculator

```php
final class WasteCalculator
{
    public static function applySheet(int $unitsBase, string $wastePct, int|string $wasteFixed): int
    {
        if ($unitsBase <= 0) return 0;
        $factor = BigDecimal::of('1')->plus(BigDecimal::of($wastePct)->dividedBy('100', 10, RoundingMode::HALF_UP));
        $withPct = BigDecimal::of($unitsBase)->multipliedBy($factor);
        return $withPct->toScale(0, RoundingMode::UP)->toInt() + (int) $wasteFixed;
    }

    public static function applyMeter(string $metersBase, string $wastePct, string $wasteFixed): string
    {
        $m = BigDecimal::of($metersBase);
        if ($m->isLessThanOrEqualTo('0')) return '0.0';
        $factor = BigDecimal::of('1')->plus(BigDecimal::of($wastePct)->dividedBy('100', 10, RoundingMode::HALF_UP));
        $withWaste = $m->multipliedBy($factor)->plus(BigDecimal::of($wasteFixed));
        // quantize do 0.1 w górę
        return (string) $withWaste->toScale(1, RoundingMode::UP);
    }
}
```

**Formuły (z pliku 03):**
- Arkuszowy: `units_final = ⌈units_base × (1 + pct/100)⌉ + fixed`. Przykład: 250, 5%, 10 → `⌈262.5⌉ + 10 = 273`.
- Metrowy: `meters_final = ⌈(meters_base × (1 + pct/100) + fixed) → 0.1 m⌉`. **`wasteFixed` dodawany po procentowym, nie po zaokrągleniu.**

### 5.4 RollSelectorCalculator

```php
public static function selectBest(string $productWidthMm, string $bleedMm, ?array $availableMaterialIds = null): ?MaterialSize
{
    $required = BigDecimal::of($productWidthMm)->plus(BigDecimal::of('2')->multipliedBy($bleedMm));
    $q = MaterialSize::query()
        ->whereHas('material', fn ($m) => $m->where('material_kind', 'roll'))
        ->whereHas('mediaFormat', fn ($mf) => $mf->where('type', 'roll'))
        ->whereHas('mediaFormat', fn ($mf) => $mf->whereRaw('width_mm >= ?', [(string) $required]))
        ->orderBy('media_format.width_mm', 'asc')
        ->orderBy('cost_per_unit', 'asc');

    if ($availableMaterialIds) $q->whereIn('material_id', $availableMaterialIds);
    return $q->first();
}
```

Zwraca najwęższą pasującą rolkę o najniższym koszcie. Nie używany bezpośrednio przez `LinearMeterEngine` — utility dla sugestii.

---

## 6. SettingResolver — dziedziczenie kaskadowe

**Plik:** `app/Services/Pricing/SettingResolver.php`

4 mapy fallbacków:

```php
final class SettingResolver
{
    // Mapa 1: ProductOverride → GlobalSetting (9 pól)
    private const OVERRIDE_TO_GLOBAL = [
        'margin_base_pct'     => 'default_margin_base_pct',
        'margin_threshold'    => 'default_margin_threshold',
        'margin_above_pct'    => 'default_margin_above_pct',
        'waste_pct'           => 'default_waste_pct',
        'waste_fixed'         => 'default_waste_fixed',
        'waste_meter_pct'     => 'default_waste_meter_pct',
        'waste_meter_fixed'   => 'default_waste_meter_fixed',
        'bleed_mm'            => 'bleed_mm',
        'sheet_margin_mm'     => 'sheet_margin_mm',
    ];

    public function resolve(Product $product, string $field): string
    {
        // Mapa 1
        if (isset(self::OVERRIDE_TO_GLOBAL[$field])) {
            $override = $product->override;
            if ($override && $override->{$field} !== null) {
                return (string) $override->{$field};
            }
            return (string) GlobalSetting::current()->{self::OVERRIDE_TO_GLOBAL[$field]};
        }

        // Mapa 2: pattern_multiplier
        if ($field === 'pattern_multiplier') {
            return (string) ($product->override?->pattern_multiplier
                ?? PatternSetting::current()->default_multiplier);
        }

        // Mapa 3: projekt (pola bezpośrednio na Product!)
        if ($field === 'project_base_price') {
            return (string) ($product->project_base_price
                ?? ProjectSetting::current()->default_base_price);
        }
        if ($field === 'project_price_per_page') {
            return (string) ($product->project_price_per_page
                ?? ProjectSetting::current()->default_price_per_page);
        }

        // Mapa 4: custom format (7 pól bezpośrednio na Product!)
        static $customFormatMap = [
            'custom_format_min_w_mm'     => 'default_min_w_mm',
            'custom_format_max_w_mm'     => 'default_max_w_mm',
            'custom_format_default_w_mm' => 'default_w_mm',
            'custom_format_min_h_mm'     => 'default_min_h_mm',
            'custom_format_max_h_mm'     => 'default_max_h_mm',
            'custom_format_default_h_mm' => 'default_h_mm',
            'custom_format_default_unit' => 'default_unit',
        ];
        if (isset($customFormatMap[$field])) {
            return (string) ($product->{$field} ?? CustomFormatSetting::current()->{$customFormatMap[$field]});
        }

        throw new \InvalidArgumentException("Nieznane pole: {$field}");
    }
}
```

Singletony używają `SingletonTrait` z metodą `::current()` (alias `load()`):

```php
trait SingletonTrait
{
    public static function current(): static
    {
        return Cache::remember(static::class.':pk1', 3600, fn () => static::firstOrCreate(['id' => 1]));
    }

    protected static function boot(): void
    {
        parent::boot();
        static::saving(fn ($m) => $m->id = 1);
        static::deleting(fn ($m) => throw new \RuntimeException('Nie można usunąć singletona'));
        static::saved(fn () => Cache::tags(['pricing'])->flush());
    }
}
```

---

## 7. CostPriceTrait — 8 pól

**Plik:** `app/Traits/CostPriceTrait.php`

```php
trait CostPriceTrait
{
    // (migracja dodaje 8 kolumn DECIMAL(10,2) NULL)

    protected function castsCostPrice(): array
    {
        return [
            'cost_total'        => 'decimal:2',
            'cost_per_sheet'    => 'decimal:2',
            'cost_per_meter'    => 'decimal:2',
            'cost_per_piece'    => 'decimal:2',
            'price_total'       => 'decimal:2',
            'price_per_sheet'   => 'decimal:2',
            'price_per_meter'   => 'decimal:2',
            'price_per_piece'   => 'decimal:2',
        ];
    }
}
```

Używany przez: `Product`, `ParameterOption`, `ZadrukOption`.

**`SimpleParameterOption` NIE dziedziczy** — ma jedno pole `price DECIMAL(10,2) DEFAULT 0` (produkty proste nie mają marży).

**Reguła NULL:** w pipeline `NULL → '0'` (`$item->cost_total ?? '0'`).

---

## 8. Mapowanie Django → Laravel (kompletne)

### Modele singletonowe (pk=1)

| Django | Laravel | Trait |
|--------|---------|-------|
| `GlobalSetting` | `App\Models\Core\GlobalSetting` | `SingletonTrait` |
| `PatternSetting` | `App\Models\Parameters\PatternSetting` | `SingletonTrait` |
| `ProjectSetting` | `App\Models\Parameters\ProjectSetting` | `SingletonTrait` |
| `CustomFormatSetting` | `App\Models\Parameters\CustomFormatSetting` | `SingletonTrait` |

### Modele parametrów

| Django | Laravel |
|--------|---------|
| `MediaFormat` | `App\Models\Parameters\MediaFormat` |
| `Format` | `App\Models\Parameters\Format` |
| `Material` | `App\Models\Parameters\Material` |
| `MaterialSize` | `App\Models\Parameters\MaterialSize` |
| `ZadrukOption` | `App\Models\Parameters\ZadrukOption` + `CostPriceTrait` |
| `TimeOption` | `App\Models\Parameters\TimeOption` |
| `PageConfig` | `App\Models\Parameters\PageConfig` |
| `Parameter` | `App\Models\Parameters\Parameter` |
| `ParameterOption` | `App\Models\Parameters\ParameterOption` + `CostPriceTrait` |
| `SimpleParameter` | `App\Models\Parameters\SimpleParameter` |
| `SimpleParameterOption` | `App\Models\Parameters\SimpleParameterOption` **bez** `CostPriceTrait` |

### Modele produktów

| Django | Laravel |
|--------|---------|
| `Product` | `App\Models\Products\Product` + `CostPriceTrait` |
| `ProductOverride` | `App\Models\Products\ProductOverride` (hasOne) |
| `ProductFormat` / `ProductMaterial` / `ProductZadrukOption` / `ProductParameterOption` | `App\Models\Products\Product*` (pivot z `role`) |
| `ProductTimeOption` | `App\Models\Products\ProductTimeOption` (z `multiplier_override` + `business_days` + accessor `effective_multiplier`) |
| `ProductPageConfig` | `App\Models\Products\ProductPageConfig` |
| `ProductSimpleParameterOption` | `App\Models\Products\ProductSimpleParameterOption` |
| `SimpleProductDiscount` | `App\Models\Products\SimpleProductDiscount` (hasOne) |

### Modele wykluczeń

| Django | Laravel |
|--------|---------|
| `ExclusionRule` | `App\Models\Exclusions\ExclusionRule` |
| `ExclusionCondition` | `App\Models\Exclusions\ExclusionCondition` |
| `ExclusionAction` | `App\Models\Exclusions\ExclusionAction` |

### Logika

| Python | PHP (Laravel) |
|--------|---------------|
| `PricingPipeline` | `App\Services\Pricing\PricingPipeline` |
| `PricingPipeline.calculate()` | `PricingPipeline::calculate(Product, CalculationConfig): PricingResult` |
| `_sum_cost_fields` / `_sum_price_fields` | metody private w `PricingPipeline` |
| `_resolve_time_multiplier` | metoda private |
| `CalculationEngine` ABC | interfejs `App\Services\Pricing\Engines\CalculationEngine` |
| `SheetEngine` / `LinearMeterEngine` / `MultipageEngine` | klasy w `App\Services\Pricing\Engines\*` |
| `get_engine()` | `EngineResolver::get()` |
| `calculate_imposition` | `ImpositionCalculator::calculate` |
| `calculate_margin` | `MarginCalculator::calculate` |
| `apply_waste` / `apply_meter_waste` | `WasteCalculator::applySheet` / `applyMeter` |
| `select_best_roll_size` | `RollSelectorCalculator::selectBest` |
| `resolve_setting` | `SettingResolver::resolve` |
| `resolve_project_pages` | `ProjectPageResolver::resolve` |
| `calculate_simple_product_price` | `SimplePricingService::calculate` |
| `evaluate_exclusions` | `ExclusionResolver::evaluate` |

### Value Objects (readonly)

| Python dataclass | PHP readonly class |
|------------------|---------------------|
| `CalculationConfig` | `App\Services\Pricing\ValueObjects\CalculationConfig` |
| `EngineResult` | `App\Services\Pricing\ValueObjects\EngineResult` |
| `PricingResult` | `App\Services\Pricing\ValueObjects\PricingResult` |
| `SimplePricingResult` | `App\Services\Pricing\ValueObjects\SimplePricingResult` |
| `UserSelection` | `App\Services\Pricing\ValueObjects\UserSelection` |
| `ExclusionResult` | `App\Services\Pricing\ValueObjects\ExclusionResult` |

### Traits i enums

| Python | Laravel |
|--------|---------|
| `CostPriceMixin` (abstract model) | `App\Traits\CostPriceTrait` |
| Singleton via `load()` + `save()` | `App\Traits\SingletonTrait` |
| `ProductType` enum | `App\Enums\ProductType` |
| `EngineType` enum | `App\Enums\EngineType` |
| `MediaFormatType` enum | `App\Enums\MediaFormatType` |
| `MaterialKind` enum | `App\Enums\MaterialKind` |
| `CostUnit` enum | `App\Enums\CostUnit` |
| `MultipageType` enum | `App\Enums\MultipageType` |
| `LengthUnit` enum | `App\Enums\LengthUnit` |
| `PartRole` enum (+ alias `MaterialRole`) | `App\Enums\PartRole` |
| `ExclusionActionType` enum | `App\Enums\ExclusionActionType` |

---

## 9. Produkty proste — `SimplePricingService`

**Plik:** `app/Services/Pricing/SimplePricingService.php`

Wzór (literalnie z pliku 05 źródła):

```php
public function calculate(Product $product, int $quantity, array $selectedOptionIds): SimplePricingResult
{
    $discount = $product->simpleProductDiscount;
    if (!$discount) throw new PriceCalculationException("Brak SimpleProductDiscount dla produktu {$product->name}");

    $base      = BigDecimal::of($discount->base_unit_price);
    $startQty  = $discount->discount_start_qty;
    $pct       = BigDecimal::of($discount->discount_percent_per_unit);
    $minPrice  = BigDecimal::of($discount->minimum_unit_price);

    // Opcje proste (poza rabatem)
    $options = ProductSimpleParameterOption::where('product_id', $product->id)
        ->whereIn('id', $selectedOptionIds)
        ->with('simpleParameterOption')
        ->get();
    $optionsSum = BigDecimal::of('0');
    foreach ($options as $row) {
        $optionsSum = $optionsSum->plus($row->simpleParameterOption->price);
    }

    // Rabat wykładniczy
    if ($quantity < $startQty) {
        $discountedBase = $base;
        $applied = false;
        $hitMin  = false;
    } else {
        $steps = $quantity - $startQty + 1;
        $oneMinus = BigDecimal::of('1')->minus($pct->dividedBy('100', 20, RoundingMode::HALF_UP));
        $factor   = $oneMinus->power($steps);                    // (1 - pct/100)^steps
        $discountedBase = $base->multipliedBy($factor);
        $applied = true;
        if ($discountedBase->isLessThan($minPrice)) {
            $discountedBase = $minPrice;
            $hitMin = true;
        } else {
            $hitMin = false;
        }
    }

    $unitPrice  = $discountedBase->plus($optionsSum);
    $totalNet   = $unitPrice->multipliedBy($quantity);

    return new SimplePricingResult(
        baseWithOptionsUnit: (string) $base->plus($optionsSum),
        baseUnitAfterDiscount: (string) $discountedBase,
        optionsSum: (string) $optionsSum,
        unitPriceAfterDiscount: (string) $unitPrice,
        quantity: $quantity,
        totalPriceNet: (string) $totalNet,
        discountApplied: $applied,
        discountHitMinimum: $hitMin,
        breakdown: [
            'base_unit_price'            => (string) $base,
            'base_unit_after_discount'   => (string) $discountedBase,
            'options_sum'                => (string) $optionsSum,
            'discount_start_qty'         => $startQty,
            'discount_percent_per_unit'  => (string) $pct,
            'unit_price_after_discount'  => (string) $unitPrice,
            'minimum_unit_price'         => (string) $minPrice,
        ],
    );
}
```

### Zasady (z pliku 05)

1. **Rabat dotyczy TYLKO bazy.** Opcje są poza rabatem — dodawane po zrabatowaniu.
2. **Krzywa wykładnicza:** `discounted_base = base × (1 - pct/100)^steps`, gdzie `steps = quantity − start_qty + 1`.
3. **Cap minimum:** gdy `discountedBase < minimum_unit_price` → ustaw na min.
4. **Quantity < start_qty:** rabat nie stosowany, `discountedBase = base`.
5. **Brak `SimpleProductDiscount`** → `PriceCalculationException`.

### Przykład (kubki, baza 25, start 10, pct 0.5%, min 12)

| Qty | discounted_base | unit_price (+opcja +5) | total |
|-----|-----------------|-----------------------|-------|
| 1 | 25.00 (rabat nieaktywny) | 25.00 (+5) = 30.00 | 30.00 |
| 100 | 25.00 × 0.995^91 ≈ 15.845 | 15.845 (+5) = 20.845 | 2 084.50 |
| 5000 | 12.00 (cap) | 12.00 (+5) = 17.00 | 85 000.00 |

---

## 10. Wykluczenia — `ExclusionResolver`

**Plik:** `app/Services/Pricing/ExclusionResolver.php`

```php
public function evaluate(Product $product, UserSelection $selection): ExclusionResult
{
    $rules = $product->exclusionRules()
        ->where('is_active', true)
        ->with('conditions', 'actions')
        ->orderBy('sort_order')
        ->get();

    $hiddenOptions = [];
    $hiddenParameters = [];

    foreach ($rules as $rule) {
        $allMet = $rule->conditions->every(fn ($c) => $this->conditionMet($c, $selection));
        if (!$allMet) continue;

        foreach ($rule->actions as $a) {
            if ($a->action_type === 'hide_option')     $hiddenOptions[]    = $a->target_id;
            if ($a->action_type === 'hide_parameter')  $hiddenParameters[] = $a->target_id;
        }
    }

    return new ExclusionResult(
        hiddenParameterIds: array_unique($hiddenParameters),
        hiddenOptionIds:    array_unique($hiddenOptions),
    );
}

private function conditionMet(ExclusionCondition $c, UserSelection $s): bool
{
    return match ($c->target) {
        'format'           => $s->formatId === $c->target_id,
        'material'         => in_array($c->target_id, $s->materialIds, true),
        'zadruk'           => $s->zadrukId === $c->target_id,
        'parameter_option' => in_array($c->target_id, $s->parameterOptionIds, true),
        'page_config'      => $s->pageConfigId === $c->target_id,
        'multipage_type'   => $s->multipageType === $c->target_value,
        default            => false,
    };
}
```

**`UserSelection`** (readonly):
```php
final readonly class UserSelection
{
    public function __construct(
        public ?int $formatId = null,
        public array $materialIds = [],
        public ?int $zadrukId = null,
        public ?int $pageConfigId = null,
        public ?string $multipageType = null,
        public array $parameterOptionIds = [],
    ) {}
}
```

**Wywoływane:**
1. **Frontend** (Vue composable `usePricingConfig`) — przy każdej zmianie wyboru debounce 150 ms → `POST /api/pricing/validate` → aktualizacja widoczności.
2. **Backend** przy submit — przed wywołaniem pipeline'u; gdy `hidden_option_ids` zawiera wybór użytkownika → `422` z komunikatem.

---

## 11. Struktura katalogów w `app/`

```
app/
├── Services/Pricing/
│   ├── PricingPipeline.php
│   ├── SimplePricingService.php
│   ├── SettingResolver.php
│   ├── ProjectPageResolver.php
│   ├── ExclusionResolver.php
│   ├── LoyaltyOverlay.php           ← post-pipeline
│   ├── RkwShadowCalculator.php       ← post-pipeline (shadow)
│   │
│   ├── Engines/
│   │   ├── CalculationEngine.php    (interface)
│   │   ├── SheetEngine.php
│   │   ├── LinearMeterEngine.php
│   │   ├── MultipageEngine.php
│   │   └── EngineResolver.php
│   │
│   ├── Calculators/
│   │   ├── ImpositionCalculator.php
│   │   ├── MarginCalculator.php
│   │   ├── WasteCalculator.php
│   │   └── RollSelectorCalculator.php
│   │
│   └── ValueObjects/
│       ├── CalculationConfig.php
│       ├── EngineResult.php
│       ├── PricingResult.php
│       ├── SimplePricingResult.php
│       ├── UserSelection.php
│       └── ExclusionResult.php
│
├── Models/
│   ├── Core/GlobalSetting.php
│   ├── Parameters/...               (14 modeli)
│   ├── Products/...                 (11 modeli)
│   └── Exclusions/...               (3 modele)
│
├── Traits/
│   ├── CostPriceTrait.php
│   └── SingletonTrait.php
│
└── Enums/
    ├── ProductType.php
    ├── EngineType.php
    ├── MediaFormatType.php
    ├── MaterialKind.php
    ├── CostUnit.php
    ├── MultipageType.php
    ├── LengthUnit.php
    ├── PartRole.php
    └── ExclusionActionType.php
```

---

## 12. Integracja z workflow zamówienia

```
OrderCreated (event)
    ↓
 FSM: NOWE → WYCENA (transition)
    ↓
 🔥 PricingPipeline::calculate() lub SimplePricingService::calculate() 🔥
    ↓
 new KalkulacjaCeny{config_snapshot, engine_result, breakdown, final_price_net, ...}
    ↓
 PriceCalculated (event)
    ├── CreateProformaIfRequired (Finance)
    ├── NotifyClientAboutQuote (CommHub)
    ├── SavePricingSnapshot
    └── UpdateOrderToWaitingStep
    ↓
 FSM: WYCENA → OCZEKUJE_NA_PLIKI (lub OCZEKUJE_NA_PLATNOSC)
```

**Każde przeliczenie = NOWY wiersz `kalkulacje_ceny`.** Nigdy UPDATE. Aktywny snapshot: `MAX(id)` dla `(zamowienie_id, pozycja_id)`.

---

## 13. API endpointy

Wszystkie chronione `auth` (Fortify) + `throttle:60,1` (60 requestów/minutę per użytkownik). W module Shop (Faza 3) dodatkowo `guest` + `throttle:30,1` dla niezalogowanych.

### `POST /api/pricing/calculate`

**Body:** JSON zgodny z `CalculationConfig` (+ `product_id`).

**Response 200:**
```json
{
  "engine_result": { "unit_type": "sheet", "units_consumed": "273", "imposition": 2, "material_cost": "95.55", "print_cost": "54.60", "details": {...} },
  "total_cost": "150.15",
  "margin_amount": "150.15",
  "base_price": "300.30",
  "final_price_net": "300.30",
  "final_price_gross": "369.37",
  "vat": "69.07",
  "per_piece_net": "0.6006",
  "breakdown": { ... }
}
```

### `POST /api/pricing/calculate-simple`

**Body:** `{ "product_id": int, "quantity": int, "option_ids": [int,int,...] }`

**Response:** `SimplePricingResult` jako JSON.

### `POST /api/pricing/grid`

**Body:** `{ "product_id": int, "config": { ... jak calculate bez quantity i time_option_id }, "qty_brackets": [10,20,50,100,250,500,1000,2500,5000], "custom_qty": null }`

**Response:** macierz:
```json
{
  "columns": [
    { "time_option_id": 1, "name": "Standard", "multiplier": "1.00", "business_days": 5, "delivery_date": "2026-04-30", "is_default": true },
    { "time_option_id": 2, "name": "Express", "multiplier": "1.30", "business_days": 3, "delivery_date": "2026-04-28", "is_default": false }
  ],
  "rows": [
    { "qty": 10, "cells": { "1": { "price": "...", "price_gross": "...", "per_piece": "...", "per_piece_gross": "..." }, "2": {...} } },
    ...
  ],
  "custom_row": null | { "qty": 37, "cells": {...} }
}
```

**Optymalizacja:** wszystkie kombinacje liczone w jednym requeście → 18 wywołań `PricingPipeline::calculate` per grid. Cache `pricing:grid:{sha1(config)}` na 5 min.

### `POST /api/pricing/validate`

**Body:** `{ "product_id": int, "selection": UserSelection }`

**Response:** `{ "hidden_option_ids": [...], "hidden_parameter_ids": [...] }`

### `GET /api/pricing/parameter-breakdown`

**Body (query):** `config` jako JSON.

**Response:** per wybrany parametr:
```json
[
  { "parameter_option_id": 5, "name": "Folia Mat", "role": "common", "cost": "0.00", "price": "54.60", "total": "54.60" },
  ...
]
```

Pokazywane w `<details>Szczegóły kalkulacji</details>` konfiguratora.

---

## 14. Overlays poza pipeline

### 14.1 LoyaltyOverlay

```php
public function apply(PricingResult $result, Klient $k): string
{
    $tier = $k->progLojalnosciowy;
    if (!$tier) return $result->finalPriceNet;
    return (string) BigDecimal::of($result->finalPriceNet)
        ->multipliedBy(BigDecimal::of('1')->minus(BigDecimal::of($tier->discount_percent)->dividedBy('100', 10, RoundingMode::HALF_UP)));
}
```

**Krytyczne:** stosowany **po** `PricingPipeline` — nigdy wewnątrz. `kalkulacje_ceny` zapisuje osobno `loyalty_discount_percent` i `final_price_net_after_loyalty`.

### 14.2 RkwShadowCalculator (COGS zarządcze)

Dla księgowości — liczy „prawdziwy" koszt własny (RKW) równolegle do pipeline'u cenowego. Używa tych samych `engineResult.materialCost` + `printCost` + wszystkie `cost_*` + koszty maszyn (z `maszyny.cost_per_hour × actual_time`) + koszty operatorów.

Wynik trafia do raportów (Module 14), **nigdy nie wpływa na cenę klienta**.

---

## 15. 49 testów do przepisania 1:1

Z `iwp_calc_generator-main/apps/pricing/tests/`:

| Plik Python | Plik Laravel Feature test |
|-------------|---------------------------|
| `test_calculators.py` | `tests/Feature/Pricing/CalculatorTest.php` |
| `test_simple_pricing.py` | `tests/Feature/Pricing/SimplePricingTest.php` |
| `test_pipeline_integration.py` | `tests/Feature/Pricing/PipelineTest.php` |
| `test_multipage_engine.py` | `tests/Feature/Pricing/MultipageEngineTest.php` |

### CalculatorTest (sekcja z pliku 07 legacy)
- Impozycja A4 na SRA3 = 2.
- Impozycja wizytówek = 20.
- Impozycja DL = 6.
- Impozycja: mały bleed, duży margines → mniejszy fit.
- Marża poniżej progu.
- Marża powyżej progu (dwupoziomowa).
- Marża: koszt = 0 → marża = 0.
- Odpad arkuszowy: % + stały.
- Odpad metrowy: % + stały + zaokrąglenie do 0.1.

### SimplePricingTest
- Brak rabatu (qty < start).
- Rabat aktywny, nie cap.
- Rabat → cap minimum.
- Opcje poza rabatem.
- Opcje nie schodzą poniżej minimum bazy.
- Brak `SimpleProductDiscount` → `PriceCalculationException`.

### PipelineTest (integracyjne)
- Ulotki A4 / 500 szt. — cena dodatnia, per-piece w zakresie.
- Większy nakład → więcej arkuszy → większa cena.
- Dwustronny droższy od jednostronnego.
- Express = Standard × 1.30.
- Parametr (Folia Mat) podnosi cenę.
- Katalog szyty 4+8 → `project_price = 170 PLN`.
- Więcej stron → większy project_price.
- `order_project = false` → `project_price = 0`.
- Kubki: rabat aktywny ale nie cap przy qty=100.
- Kubki: cap minimum przy qty=5000.
- Kubki: opcja podnosi bazę.
- Kubki: opcja jest poza minimum rabatu.

### MultipageEngineTest
- 2 sekcje (cover + interior) — osobne materiały.
- `sheets_per_piece = ceil(pages/2)`.
- Zadruk wspólny od sumy arkuszy.
- Brak `page_config` → throw.
- Brak materiału cover → throw.
- Brak materiału interior → throw.
- Sekcja z `pages = 0` → skipped.

Szczegóły strategii: [12_STRATEGIA_TESTOWA.md](./12_STRATEGIA_TESTOWA.md).

---

## 16. Krytyczne detale przenoszenia (z pliku 07 legacy)

### ✅ 16.1 Decimal — zawsze BigDecimal / bcmath

- `brick/math\BigDecimal` wszędzie w `Services/Pricing/*`.
- Alternatywa: `bcmath` (wbudowany PHP), ale mniej czytelny → preferujemy `brick/math`.
- **Nigdy `float`** — błędy zaokrągleń rujnują pricing.
- Kolumny DB: `DECIMAL(x,y)`; cast Eloquent `decimal:y`; w PHP jako `string`.

### ✅ 16.2 Zaokrąglenia

| Python | PHP |
|--------|-----|
| `math.ceil()` dla impozycji | `BigDecimal::toScale(0, RoundingMode::UP)->toInt()` lub `(int) ceil($v)` na int |
| `int(a/b)` (floor integer) | `intdiv($a, $b)` albo `(int) floor()` albo `BigDecimal::dividedBy(.., 0, RoundingMode::DOWN)->toInt()` — **obcinanie**, nie zaokrąglanie |
| `Decimal.quantize("0.1", "ROUND_UP")` | `BigDecimal::toScale(1, RoundingMode::UP)` |

### ✅ 16.3 Role parametrów

- `COMMON` × `engineResult.unitsConsumed` z `engineResult.unitType`.
- `COVER` × `sections.cover.sheets_final`, typ `"sheet"`.
- `INTERIOR` × `sections.interior.sheets_final`, typ `"sheet"`.

### ✅ 16.4 Kolejność kroków pipeline

```
cost_* → PRE-margin (kroki 2–4)
marża  → na total_cost (krok 5)
base_price = total_cost + margin (krok 6)
pattern_multiplier → na base_price (krok 7)
price_* → POST-margin i PO wzorach (kroki 8–9)
project_price → POST-margin (krok 10)
time_multiplier → NA KOŃCU, na wszystko (krok 11–12)
```

### ✅ 16.5 MultipageEngine: 1 arkusz = 2 strony

`sheets_per_piece = (int) ceil($pages / 2)` — **nie zapomnieć!**
`sheets_base = (int) ceil($quantity * $sheetsPerPiece / $imposition)`.

### ✅ 16.6 Rabat wykładniczy (simple) — dotyczy tylko bazy

`options_sum` jest poza rabatem — dodawane PO rabacie. Nawet gdy baza spadnie do minimum, opcje podnoszą cenę.

Formuła: `$factor = $oneMinus->power($steps)` gdzie `$oneMinus = 1 - pct/100`.

### ✅ 16.7 `Product` dziedziczy CostPriceTrait

Produkt sam ma 8 pól. Wchodzą do `product_extra_cost` (krok 4) i `product_extra_price` (krok 8). Zazwyczaj puste.

### ✅ 16.8 SettingResolver — 4 mapy

- `ProductOverride → GlobalSetting` (9 pól).
- `ProductOverride.pattern_multiplier → PatternSetting.default_multiplier`.
- `Product.project_*` → `ProjectSetting.default_*` (**nie** przez override, bezpośrednio na Product).
- `Product.custom_format_*` → `CustomFormatSetting.default_*` (7 pól, bezpośrednio na Product).
- `ProductTimeOption.multiplier_override → TimeOption.multiplier` (5-ty mapping, lokalnie w `resolveTimeMultiplier`).

### ✅ 16.9 Konfigurator: HTMX → Inertia+Vue

- Stary: Django HTMX partial HTML.
- Nowy: `POST /api/pricing/*` → JSON → Vue component reaktywnie renderuje.
- Grid cenowy liczymy po stronie serwera (jeden request z macierzą).
- Debounce zmian w Vue (150–300 ms) → request.

### ✅ 16.10 Import CSV → Laravel

- Pandas → `league/csv`.
- Transakcja atomowa → `DB::transaction()`.
- 2-fazowy workflow (preview + commit) — zachować.

---

## 17. Stałe domyślne (z `seed_demo`)

| Ustawienie | Wartość | Źródło |
|------------|---------|--------|
| Marża bazowa | 100% | `global_setting.default_margin_base_pct` |
| Próg marży | 500 PLN | `global_setting.default_margin_threshold` |
| Marża powyżej | 30% | `global_setting.default_margin_above_pct` |
| Odpad arkuszowy | 5% | `global_setting.default_waste_pct` |
| Odpad stały (ark.) | 10 | `global_setting.default_waste_fixed` |
| Odpad metrowy | 5% | `global_setting.default_waste_meter_pct` |
| Odpad stały (m.) | 0 | `global_setting.default_waste_meter_fixed` |
| Bleed | 2 mm | `global_setting.bleed_mm` |
| Sheet margin | 5 mm | `global_setting.sheet_margin_mm` |
| Pattern multiplier | 0.70 | `pattern_setting.default_multiplier` |
| Projekt baza | 50 PLN | `project_setting.default_base_price` |
| Projekt /str | 10 PLN | `project_setting.default_price_per_page` |
| VAT | 23% | hardcoded `config/pricing.php` → `'vat_rate' => '0.23'` |
| Progi nakładu | (10,20,50,100,250,500,1000,2500,5000) | `config/pricing.php` → `'qty_brackets'` |
| Domyślny nakład (calc) | 100 | `config/pricing.php` |
| Domyślny nakład (simple) | 1 | `config/pricing.php` |

`seed_demo` z Django przeniesiony do `database/seeders/PricingDemoSeeder.php` — daje 3 produkty kalkulowane + 1 prosty (kubki) + komplet ustawień. Używane w testach i dev onboardingu.

---

## 18. Checklist implementacji (kolejność)

### Faza A: Migracje + Modele (bez logiki) — 1 tydzień
1. Migracje dla wszystkich tabel sekcji 4 (core + parameters + products + exclusions).
2. Eloquent modele z relacjami, walidacjami, accessorami.
3. `CostPriceTrait` i `SingletonTrait`.
4. Enums (PHP 8.1 native).
5. Seeder `PricingDemoSeeder`.

### Faza B: Logika cenowa (Services) — 2 tygodnie
1. `SettingResolver` (+ testy unit).
2. Kalkulatory: Imposition, Margin, Waste, RollSelector (+ testy unit).
3. `SheetEngine` (+ test unit).
4. `LinearMeterEngine` (+ test unit).
5. `MultipageEngine` (+ test integration).
6. `PricingPipeline` (+ test integration, wszystkie scenariusze PipelineTest).
7. `SimplePricingService` (+ test unit, wszystkie scenariusze SimplePricingTest).
8. `ProjectPageResolver`.

### Faza C: API + integracja — 1 tydzień
1. Endpointy `/api/pricing/calculate`, `/calculate-simple`, `/grid`, `/validate`, `/parameter-breakdown`.
2. Integracja z FSM zamówienia.
3. Event `PriceCalculated` + listener `SavePricingSnapshot`.
4. LoyaltyOverlay.

### Faza D: Admin w Filamencie — 1 tydzień
1. Zasoby Filament dla wszystkich tabel pricingu.
2. Import CSV 2-fazowy (preview + commit).
3. Widok „przelicz cenę" na pozycji zamówienia.

### Faza E: Frontend konfigurator (Faza 2–3 projektu) — 2 tygodnie
1. Vue composable `usePricingConfig` (reaktywne wykluczenia, debounce).
2. Strona produktu `/shop/product/{slug}` z konfiguratorem.
3. Grid cenowy + parameter breakdown UI.
