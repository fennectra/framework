<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\RoleRequest;
use App\Dto\Auth\RoleResponse;
use App\Models\Permission;
use App\Models\Role;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\DB;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class RoleController
{
    #[ApiDescription('List all roles')]
    #[ApiStatus(200, 'Roles list')]
    public function index(): array
    {
        $roles = Role::query()->orderBy('name', 'ASC')->get();

        $data = array_map(function ($role) {
            $permissions = $role->permissions();
            $permissionNames = array_map(fn ($p) => $p->name, $permissions);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $permissionNames,
            ];
        }, $roles);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Show a role')]
    #[ApiStatus(200, 'Role found')]
    #[ApiStatus(404, 'Role not found')]
    public function show(string $id): array
    {
        $role = Role::findOrFail((int) $id);
        $permissions = $role->permissions();
        $permissionNames = array_map(fn ($p) => $p->name, $permissions);

        return [
            'status' => 'ok',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $permissionNames,
            ],
        ];
    }

    #[ApiDescription('Create a role')]
    #[ApiStatus(201, 'Role created')]
    #[ApiStatus(422, 'Validation error')]
    public function store(RoleRequest $request): array
    {
        // DTO validated automatically by Router injection

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $role->toArray(),
        ];
    }

    #[ApiDescription('Update a role')]
    #[ApiStatus(200, 'Role updated')]
    #[ApiStatus(404, 'Role not found')]
    #[ApiStatus(422, 'Validation error')]
    public function update(string $id, RoleRequest $request): array
    {
        // DTO validated automatically by Router injection

        $role = Role::findOrFail((int) $id);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $role->toArray(),
        ];
    }

    #[ApiDescription('Delete a role')]
    #[ApiStatus(200, 'Role deleted')]
    #[ApiStatus(404, 'Role not found')]
    public function delete(string $id): array
    {
        $role = Role::findOrFail((int) $id);
        $role->delete();

        return [
            'status' => 'ok',
            'message' => 'Role deleted.',
        ];
    }

    #[ApiDescription('Assign permissions to a role', 'Replaces all current permissions with the provided list.')]
    #[ApiStatus(200, 'Permissions assigned')]
    #[ApiStatus(404, 'Role not found')]
    public function assignPermissions(string $id): array
    {
        $role = Role::findOrFail((int) $id);

        $body = json_decode(file_get_contents('php://input'), true);
        $permissionIds = $body['permission_ids'] ?? [];

        if (!is_array($permissionIds)) {
            throw new HttpException(422, 'permission_ids must be an array.');
        }

        // Remove existing permissions
        DB::raw(
            'DELETE FROM role_permissions WHERE role_id = :role_id',
            ['role_id' => (int) $role->id]
        );

        // Insert new permissions
        foreach ($permissionIds as $permissionId) {
            $permission = Permission::find((int) $permissionId);
            if ($permission) {
                DB::raw(
                    'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)',
                    ['role_id' => (int) $role->id, 'permission_id' => (int) $permission->id]
                );
            }
        }

        $permissions = $role->permissions();
        $permissionNames = array_map(fn ($p) => $p->name, $permissions);

        return [
            'status' => 'ok',
            'message' => 'Permissions assigned to role.',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $permissionNames,
            ],
        ];
    }
}