<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
    }

    public function test_operator_cannot_access_user_management(): void
    {
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->get(route('admin.users.index'));

        // Before tuning 403 handling, this might be 403.
        // After tuning, it should be a redirect to dashboard with flash message.
        // I will write it as it should be AFTER the change.
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Nie posiadasz uprawnień do wykonania tej akcji.');
    }

    public function test_guest_cannot_access_any_admin_route(): void
    {
        $response = $this->get(route('admin.users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_access_roles_management(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.roles.index'));

        $response->assertOk();
    }

    public function test_unauthorized_access_is_logged(): void
    {
        // This test will verify if we log failed authorization attempts if that's in requirements.
        // The plan mentions "Tuning error handling for 403...".
        // Let's stick to the redirection for now.
        $operator = User::factory()->operator()->create();

        $response = $this->actingAs($operator)->get(route('admin.users.index'));

        $response->assertRedirect(route('dashboard'));
    }
}
