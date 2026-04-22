<?php

namespace Database\Factories;

use App\Models\Klient;
use App\Models\Zamowienie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zamowienie>
 */
class ZamowienieFactory extends Factory
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
            'numer' => 'DRK-'.date('Y').'-'.$this->faker->unique()->numberBetween(10000, 99999),
            'status' => 'NEW',
            'priorytet' => 'normal',
            'channel' => 'email',
        ];
    }
}
