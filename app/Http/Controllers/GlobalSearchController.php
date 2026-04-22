<?php

namespace App\Http\Controllers;

use App\Models\Klient;
use App\Models\User;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = (string) $request->string('query');

        if (empty($query)) {
            return response()->json([
                'Użytkownicy' => [],
                'Klienci' => [],
            ]);
        }

        $users = collect();
        if ($request->user()->can('manage_users')) {
            try {
                $users = User::search($query)->take(5)->get()->map(fn ($user) => [
                    'id' => $user->id,
                    'title' => trim($user->imie.' '.$user->nazwisko) ?: $user->email,
                    'subtitle' => $user->email,
                    'url' => route('admin.users.edit', $user->id),
                    'type' => 'Użytkownicy',
                ]);
            } catch (\Exception $e) {
                // Meilisearch niedostępny — fallback do SQL
                $users = User::where(function ($q) use ($query) {
                    $q->where('email', 'like', "%{$query}%");

                    $terms = array_filter(explode(' ', $query));
                    foreach ($terms as $term) {
                        $q->orWhere('imie', 'like', "%{$term}%")
                            ->orWhere('nazwisko', 'like', "%{$term}%");
                    }
                })
                    ->take(5)
                    ->get()
                    ->map(fn ($user) => [
                        'id' => $user->id,
                        'title' => trim($user->imie.' '.$user->nazwisko) ?: $user->email,
                        'subtitle' => $user->email,
                        'url' => route('admin.users.edit', $user->id),
                        'type' => 'Użytkownicy',
                    ]);
            }
        }

        $clients = collect();
        if ($request->user()->can('manage_clients')) {
            try {
                $clients = Klient::search($query)->take(5)->get()->map(fn ($klient) => [
                    'id' => $klient->id,
                    'title' => $klient->imie_nazwa,
                    'subtitle' => $klient->email_glowny,
                    'url' => route('admin.klienci.edit', $klient->id),
                    'type' => 'Klienci',
                ]);
            } catch (\Exception $e) {
                // Meilisearch niedostępny — fallback do SQL
                $clients = Klient::where(function ($q) use ($query) {
                    $q->where('imie_nazwa', 'like', "%{$query}%")
                        ->orWhere('email_glowny', 'like', "%{$query}%");
                })
                    ->take(5)
                    ->get()
                    ->map(fn ($klient) => [
                        'id' => $klient->id,
                        'title' => $klient->imie_nazwa,
                        'subtitle' => $klient->email_glowny,
                        'url' => route('admin.klienci.edit', $klient->id),
                        'type' => 'Klienci',
                    ]);
            }
        }

        return response()->json([
            'Użytkownicy' => $users,
            'Klienci' => $clients,
        ]);
    }
}
