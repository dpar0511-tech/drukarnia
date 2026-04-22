<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Definicja uprawnień dla poszczególnych ról
        $roles = [
            SystemRole::Admin->value => [
                'manage_users',
                'manage_roles',
                'manage_orders',
                'manage_clients',
                'operate_machines',
                'manage_finances',
                'view_reports',
                'design_files',
                'view_own_orders',
                'view_communication',
                'manage_communication',
            ],
            SystemRole::Menedzer->value => [
                'manage_orders',
                'manage_clients',
                'view_reports',
                'view_communication',
                'manage_communication',
            ],
            SystemRole::Projektant->value => [
                'design_files',
            ],
            SystemRole::Operator->value => [
                'operate_machines',
                'view_reports',
            ],
            SystemRole::Ksiegowosc->value => [
                'manage_finances',
                'view_reports',
            ],
            SystemRole::Klient->value => [
                'view_own_orders',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            foreach ($permissions as $permissionName) {
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            }

            $role->syncPermissions($permissions);
        }
    }
}
