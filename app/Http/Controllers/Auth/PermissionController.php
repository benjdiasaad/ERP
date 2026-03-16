<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\PermissionResource;
use App\Models\Auth\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    /**
     * GET /permissions
     * Returns all permissions, optionally filtered by module.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Permission::class);

        $query = Permission::orderBy('module')->orderBy('slug');

        if ($request->filled('module')) {
            
            $query->where('module', $request->string('module'));
        }

        $permissions = $query->paginate($request->integer('per_page', 50));

        return PermissionResource::collection($permissions);
    }

    /**
     * GET /permissions/{permission}
     */
    public function show(Permission $permission): PermissionResource
    {
        $this->authorize('view', Permission::class);

        return new PermissionResource($permission->load('roles'));
    }

    /**
     * GET /permissions/grouped
     * Returns all permissions grouped by module.
     */
    public function grouped(): JsonResponse
    {
        $this->authorize('viewAny', Permission::class);

        $grouped = $this->permissionService->getAllGroupedByModule();

        return response()->json(['data' => $grouped]);
    }
}
