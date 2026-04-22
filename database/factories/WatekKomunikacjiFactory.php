<?php

namespace Database\Factories;

use App\Enums\CommunicationThreadStatus;
use App\Models\Klient;
use App\Models\WatekKomunikacji;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatekKomunikacji>
 */
class WatekKomunikacjiFactory extends Factory
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
            'temat' => $this->faker->sentence(),
            'status' => CommunicationThreadStatus::Nowy,
        ];
    }
}
