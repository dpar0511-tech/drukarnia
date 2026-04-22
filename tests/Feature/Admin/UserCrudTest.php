<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::findOrCreate('manage_users', 'web');
        Permission::findOrCreate('manage_roles', 'web');

        $this->adminRole = Role::findOrCreate('Admin', 'web');
        $this->adminRole->givePermissionTo('manage_users');
        $this->adminRole->givePermissionTo('manage_roles');

        $this->admin = User::factory()->create();
        $this->admin->assignRole($this->adminRole);
    }

    public function test_admin_can_view_users_list(): void
    {
        $response = $this->actingAs($this->admin)->getJson(route('admin.users.index'));

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_view_users_list(): void
    {
        $userRole = Role::findOrCreate('User', 'web');
        $user = User::factory()->create();
        $user->assignRole($userRole);

        $response = $this->actingAs($user)->getJson(route('admin.users.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $role = Role::findOrCreate('Menedżer', 'web');

        $response = $this->actingAs($this->admin)->postJson(route('admin.users.store'), [
            'imie' => 'Jan',
            'nazwisko' => 'Kowalski',
            'email' => 'jan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $role->id,
            'aktywny' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'jan@example.com']);
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $user->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson(route('admin.users.destroy', $this->admin->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
