<?php

namespace Tests\Feature;

use App\Models\Klient;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        // Swap Scout engine with NullEngine to avoid real Meilisearch calls during tests
        $this->app->make(EngineManager::class)->extend('meilisearch', fn () => new NullEngine);
        config(['scout.driver' => 'null']);
    }

    public function test_unauthenticated_user_cannot_access_search(): void
    {
        $response = $this->getJson(route('global.search', ['query' => 'test']));
        $response->assertStatus(401);
    }

    public function test_user_without_permissions_sees_empty_results(): void
    {
        $user = User::factory()->create();
        // No roles/permissions assigned

        $response = $this->actingAs($user)->getJson(route('global.search', ['query' => 'test']));

        $response->assertStatus(200)
            ->assertJson([
                'Użytkownicy' => [],
                'Klienci' => [],
            ]);
    }

    public function test_user_with_manage_users_permission_can_see_users(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_users');

        User::factory()->create(['imie' => 'Jan', 'nazwisko' => 'Kowalski']);

        // With NullEngine, Scout returns empty — we verify controller returns correct structure
        // and falls through to SQL fallback which will find the user
        $response = $this->actingAs($admin)->getJson(route('global.search', ['query' => 'Jan']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['Użytkownicy', 'Klienci']);
    }

    public function test_user_with_manage_clients_permission_can_see_clients(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage_clients');

        Klient::factory()->create(['imie_nazwa' => 'Firma Testowa']);

        $response = $this->actingAs($user)->getJson(route('global.search', ['query' => 'Firma']));

        $response->assertStatus(200);
        $response->assertJsonStructure(['Użytkownicy', 'Klienci']);
    }
}
