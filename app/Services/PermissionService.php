<?php

namespace App\Services;

class PermissionService
{
    public static function getAllPermissions(): array
    {
        return [
            'System' => [
                'manage_users' => 'Zarządzanie użytkownikami',
                'manage_roles' => 'Zarządzanie rolami',
                'view_logs' => 'Przeglądanie logów',
            ],
            'Sprzedaż' => [
                'view_orders' => 'Przeglądanie zamówień',
                'create_orders' => 'Tworzenie zamówień',
                'edit_orders' => 'Edycja zamówień',
                'delete_orders' => 'Usuwanie zamówień',
            ],
            'Produkcja' => [
                'view_production' => 'Podgląd produkcji',
                'manage_production' => 'Zarządzanie produkcją',
            ],
            'Klienci' => [
                'view_klienci' => 'Przeglądanie klientów',
                'manage_klienci' => 'Zarządzanie klientami',
            ],
            'Magazyn' => [
                'view_magazyn' => 'Przeglądanie magazynu',
                'manage_magazyn' => 'Zarządzanie magazynem',
            ],
            'Komunikacja' => [
                'view_communication' => 'Dostęp do Inbox',
                'manage_communication' => 'Zarządzanie powiadomieniami',
            ],
        ];
    }

    public static function getFlatPermissions(): array
    {
        $flat = [];
        foreach (self::getAllPermissions() as $category => $permissions) {
            foreach ($permissions as $key => $label) {
                $flat[] = $key;
            }
        }

        return $flat;
    }
}
