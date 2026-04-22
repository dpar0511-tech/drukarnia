<?php

namespace Database\Factories;

use App\Models\Klient;
use App\Models\OsobaKontaktowa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OsobaKontaktowa>
 */
class OsobaKontaktowaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'klient_id' => Klient::factory(),
            'imie' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefon' => $this->faker->phoneNumber(),
        ];
    }
}
