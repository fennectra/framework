<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\PermissionRequest;
use App\Dto\Auth\PermissionResponse;
use App\Models\Permission;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\HttpException;
use Fennec\Core\Validator;

class PermissionController
{
    #[ApiDescription('List all permissions')]
    #[ApiStatus(200, 'Permissions list')]
    public function index(): array
    {
        $permissions = Permission::query()->orderBy('name', 'ASC')->get();

        $data = array_map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
        ], $permissions);

        return [
            'status' => 'ok',
            'data' => $data,
        ];
    }

    #[ApiDescription('Show a permission')]
    #[ApiStatus(200, 'Permission found')]
    #[ApiStatus(404, 'Permission not found')]
    public function show(string $id): array
    {
        $permission = Permission::findOrFail((int) $id);

        return [
            'status' => 'ok',
            'data' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
            ],
        ];
    }

    #[ApiDescription('Create a permission')]
    #[ApiStatus(201, 'Permission created')]
    #[ApiStatus(422, 'Validation error')]
    public function store(PermissionRequest $request): array
    {
        // DTO validated automatically by Router injection

        $permission = Permission::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $permission->toArray(),
        ];
    }

    #[ApiDescription('Update a permission')]
    #[ApiStatus(200, 'Permission updated')]
    #[ApiStatus(404, 'Permission not found')]
    #[ApiStatus(422, 'Validation error')]
    public function update(string $id, PermissionRequest $request): array
    {
        // DTO validated automatically by Router injection

        $permission = Permission::findOrFail((int) $id);

        $permission->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return [
            'status' => 'ok',
            'data' => $permission->toArray(),
        ];
    }

    #[ApiDescription('Delete a permission')]
    #[ApiStatus(200, 'Permission deleted')]
    #[ApiStatus(404, 'Permission not found')]
    public function delete(string $id): array
    {
        $permission = Permission::findOrFail((int) $id);
        $permission->delete();

        return [
            'status' => 'ok',
            'message' => 'Permission deleted.',
        ];
    }
}