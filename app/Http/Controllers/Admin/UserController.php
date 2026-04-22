<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Klient;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with(['roles', 'klient'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('imie', 'like', "%{$search}%")
                        ->orWhere('nazwisko', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->role_id, function ($query, $roleId) {
                $query->whereHas('roles', function ($q) use ($roleId) {
                    $q->where('roles.id', $roleId);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($users),
            'filters' => $request->only(['search', 'role_id']),
            'roles' => RoleResource::collection(Role::all()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'roles' => RoleResource::collection(Role::all()),
            'klienci' => Klient::select('id', 'imie_nazwa')->get(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($roleId) {
            $user->syncRoles([$roleId]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Użytkownik został utworzony.');
    }

    public function edit(User $user): Response
    {
        $user->load(['roles', 'klient', 'auditLogs' => function ($query) {
            $query->latest()->limit(20);
        }, 'auditLogs.user']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => new UserResource($user),
            'roles' => RoleResource::collection(Role::all()),
            'klienci' => Klient::select('id', 'imie_nazwa')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $roleId = $data['role_id'] ?? null;
        unset($data['role_id']);

        if ($data['password'] ?? null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($roleId) {
            $user->syncRoles([$roleId]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Dane użytkownika zostały zaktualizowane.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Nie możesz usunąć samego siebie.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'Użytkownik został usunięty.');
    }

    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Nie możesz zablokować samego siebie.');
        }

        $newStatus = ! $user->aktywny;
        $user->update(['aktywny' => $newStatus]);

        if (! $newStatus) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', 'Status użytkownika zaktualizowany.');
    }
}
