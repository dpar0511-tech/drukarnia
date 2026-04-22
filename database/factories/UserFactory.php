<?php

namespace Database\Factories;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imie' => fake()->firstName(),
            'nazwisko' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'aktywny' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'aktywny' => false,
        ]);
    }

    /**
     * Indicate that the user has a specific role.
     */
    public function withRole(string|SystemRole $role): static
    {
        $roleName = $role instanceof SystemRole ? $role->value : $role;

        return $this->afterCreating(function (User $user) use ($roleName) {
            $user->assignRole($roleName);
        });
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->withRole(SystemRole::Admin);
    }

    /**
     * Indicate that the user is an operator.
     */
    public function operator(): static
    {
        return $this->withRole(SystemRole::Operator);
    }
}
