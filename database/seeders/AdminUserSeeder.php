<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@drukarnia.local'],
            [
                'imie' => 'Super',
                'nazwisko' => 'Admin',
                'password' => Hash::make('password'),
                'aktywny' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole(SystemRole::Admin->value);
    }
}
