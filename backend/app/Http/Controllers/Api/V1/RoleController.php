<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->with('permissions')->get();

        return ApiResponse::success(RoleResource::collection($roles), 'Roles fetched successfully.');
    }

    public function permissions(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        return ApiResponse::success(Permission::query()->pluck('name'), 'Permissions fetched successfully.');
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions') ?? []);

        return ApiResponse::success(new RoleResource($role->load('permissions')), 'Role created successfully.', status: 201);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        return ApiResponse::success(new RoleResource($role->load('permissions')), 'Role updated successfully.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if (in_array($role->name, ['Super Admin', 'Store Owner'], true)) {
            return ApiResponse::error('This role cannot be deleted.', [], 422);
        }

        $role->delete();

        return ApiResponse::success(message: 'Role deleted successfully.');
    }
}
