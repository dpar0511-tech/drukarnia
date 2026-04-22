<?php

namespace Database\Seeders;

use App\Models\StatusDefinition;
use Illuminate\Database\Seeder;

class StatusDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['kod' => 'DRAFT', 'nazwa_pl' => 'Szkic', 'kolor' => '#94a3b8', 'ikona' => 'FileText', 'modul' => 'order', 'kolejnosc' => 1],
            ['kod' => 'NEW', 'nazwa_pl' => 'Nowe', 'kolor' => '#3b82f6', 'ikona' => 'Star', 'modul' => 'order', 'kolejnosc' => 2],
            ['kod' => 'WAITING_PAYMENT', 'nazwa_pl' => 'Oczekiwanie на płatność', 'kolor' => '#f59e0b', 'ikona' => 'CreditCard', 'modul' => 'order', 'kolejnosc' => 3],
            ['kod' => 'PAID', 'nazwa_pl' => 'Opłacone', 'kolor' => '#10b981', 'ikona' => 'CheckCircle', 'modul' => 'order', 'kolejnosc' => 4],
            ['kod' => 'VERIFICATION', 'nazwa_pl' => 'Weryfikacja', 'kolor' => '#6366f1', 'ikona' => 'Search', 'modul' => 'order', 'kolejnosc' => 5],
            ['kod' => 'PREPRESS', 'nazwa_pl' => 'Przygotowanie', 'kolor' => '#8b5cf6', 'ikona' => 'Layers', 'modul' => 'order', 'kolejnosc' => 6],
            ['kod' => 'PRODUCTION', 'nazwa_pl' => 'Produkcja', 'kolor' => '#f43f5e', 'ikona' => 'Cpu', 'modul' => 'order', 'kolejnosc' => 7],
            ['kod' => 'READY_FOR_SHIPPING', 'nazwa_pl' => 'Gotowe do wysyłki', 'kolor' => '#06b6d4', 'ikona' => 'Package', 'modul' => 'order', 'kolejnosc' => 8],
            ['kod' => 'SHIPPED', 'nazwa_pl' => 'Wysłane', 'kolor' => '#2dd4bf', 'ikona' => 'Truck', 'modul' => 'order', 'kolejnosc' => 9],
            ['kod' => 'COMPLETED', 'nazwa_pl' => 'Zakończone', 'kolor' => '#059669', 'ikona' => 'CheckCircle2', 'modul' => 'order', 'kolejnosc' => 10],
            ['kod' => 'CANCELLED', 'nazwa_pl' => 'Anulowane', 'kolor' => '#ef4444', 'ikona' => 'XCircle', 'modul' => 'order', 'kolejnosc' => 11],
            ['kod' => 'REJECTED', 'nazwa_pl' => 'Odrzucone', 'kolor' => '#b91c1c', 'ikona' => 'ThumbsDown', 'modul' => 'order', 'kolejnosc' => 12],
            ['kod' => 'ON_HOLD', 'nazwa_pl' => 'Wstrzymane', 'kolor' => '#6b7280', 'ikona' => 'PauseCircle', 'modul' => 'order', 'kolejnosc' => 13],
            ['kod' => 'COMPLAINT', 'nazwa_pl' => 'Reklamacja', 'kolor' => '#ea580c', 'ikona' => 'AlertTriangle', 'modul' => 'order', 'kolejnosc' => 14],
            ['kod' => 'ARCHIVED', 'nazwa_pl' => 'Zarchiwizowane', 'kolor' => '#4b5563', 'ikona' => 'Archive', 'modul' => 'order', 'kolejnosc' => 15],
            ['kod' => 'REFUNDED', 'nazwa_pl' => 'Zwrócono środki', 'kolor' => '#d946ef', 'ikona' => 'RotateCcw', 'modul' => 'order', 'kolejnosc' => 16],
        ];

        foreach ($statuses as $status) {
            StatusDefinition::updateOrCreate(
                ['kod' => $status['kod']],
                $status
            );
        }
    }
}
