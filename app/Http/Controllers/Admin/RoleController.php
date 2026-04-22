<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\PermissionService;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users')->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => RoleResource::collection($roles),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Roles/Create', [
            'availablePermissions' => PermissionService::getAllPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rola została utworzona.');
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'availablePermissions' => PermissionService::getAllPermissions(),
        ]);
    }

    public function update(StoreRoleRequest $request, Role $role)
    {
        $role->update([
            'name' => $request->name,
        ]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rola została zaktualizowana.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'Nie można usunąć roli, która jest przypisana do użytkowników.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Rola została usunięta.');
    }
}
