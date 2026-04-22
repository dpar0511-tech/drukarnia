<?php

namespace Database\Factories;

use App\Models\Klient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Klient>
 */
class KlientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'typ' => $this->faker->randomElement(['B2B', 'B2C']),
            'imie_nazwa' => $this->faker->company(),
            'email_glowny' => $this->faker->unique()->safeEmail(),
            'status' => 'aktywny',
        ];
    }
}
