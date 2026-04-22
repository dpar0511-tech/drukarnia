<?php

namespace Tests\Feature;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_users_can_be_listed_by_admin(): void
    {
        $adminRole = Role::where('name', SystemRole::Admin->value)->first();
        $operatorRole = Role::where('name', SystemRole::Operator->value)->first();

        User::factory()->count(3)->create()->each(fn ($u) => $u->assignRole($operatorRole));

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $this->withoutVite();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertStatus(200);
    }

    public function test_non_admins_cannot_access_users_list(): void
    {
        $managerRole = Role::where('name', SystemRole::Menedzer->value)->first();
        $manager = User::factory()->create();
        $manager->assignRole($managerRole);

        $this->actingAs($manager)->getJson(route('admin.users.index'))
            ->assertStatus(403);
    }

    public function test_deactivated_user_cannot_access_routes(): void
    {
        $adminRole = Role::where('name', SystemRole::Admin->value)->first();
        $user = User::factory()->create([
            'aktywny' => false,
        ]);
        $user->assignRole($adminRole);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_update_logs_activity(): void
    {
        $adminRole = Role::where('name', SystemRole::Admin->value)->first();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);

        $user = User::factory()->create(['imie' => 'OldName']);

        $admin->update(['imie' => 'NewName']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => User::class,
            'subject_id' => $admin->id,
            'event' => 'updated',
        ]);
    }

    public function test_activity_log_captures_ip_address(): void
    {
        $adminRole = Role::where('name', SystemRole::Admin->value)->first();
        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $target = User::factory()->create();

        // Execute within an HTTP request context so request()->ip() returns 127.0.0.1
        $this->actingAs($admin)->call('GET', route('admin.users.edit', $target));

        // Now manually log activity - Observer should capture IP from the previous request context
        // Actually, test that Observer enriches properties on creating
        $activity = activity()
            ->causedBy($admin)
            ->performedOn($target)
            ->withProperties(['before' => 'OldStatus', 'after' => 'NewStatus'])
            ->log('status_changed');

        $log = AuditLog::latest()->first();

        $this->assertNotNull($log, 'Activity log entry should exist');
        $this->assertNotNull($log->ip_address, 'Observer should capture ip_address in properties');
    }
}
