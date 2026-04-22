<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Enums\MessageDirection;
use App\Models\WatekKomunikacji;
use App\Models\Wiadomosc;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wiadomosc>
 */
class WiadomoscFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'watek_id' => WatekKomunikacji::factory(),
            'kierunek' => MessageDirection::Przychodzacy,
            'kanal' => MessageChannel::Email,
            'tresc' => $this->faker->paragraph(),
            'nadawca_email' => $this->faker->safeEmail(),
            'przeczytana' => false,
        ];
    }
}
