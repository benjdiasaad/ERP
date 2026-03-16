<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRoleRequest;
use App\Http\Requests\Auth\UpdateRoleRequest;
use App\Http\Resources\Auth\RoleResource;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Services\Auth\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * GET /roles
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        $companyId = auth()->user()->current_company_id;

        $roles = Role::forCompany($companyId)
            ->with('permissions')
            ->paginate($request->integer('per_page', 15));

        return RoleResource::collection($roles);
    }

    /**
     * POST /roles
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        // Company-scoped by default (not global)
        $data['company_id'] = auth()->user()->current_company_id;

        try {
            $role = $this->roleService->create($data);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new RoleResource($role->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /roles/{role}
     */
    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return new RoleResource($role->load('permissions'));
    }

    /**
     * PUT /roles/{role}
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        try {
            $role = $this->roleService->update($role, $data);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new RoleResource($role->load('permissions')))->response();
    }

    /**
     * DELETE /roles/{role}
     */
    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        try {
            $this->roleService->delete($role);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /roles/{role}/permissions — assign permissions (additive)
     */
    public function assignPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        try {
            $role = $this->roleService->assignPermissions($role, $data['permission_ids']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new RoleResource($role))->response();
    }

    /**
     * DELETE /roles/{role}/permissions — revoke permissions
     */
    public function revokePermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'permission_ids' => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = $this->roleService->revokePermissions($role, $data['permission_ids']);

        return (new RoleResource($role))->response();
    }

    /**
     * PUT /roles/{role}/permissions — sync (replace all) permissions
     */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'permission_ids'   => ['required', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        try {
            $role = $this->roleService->syncPermissions($role, $data['permission_ids']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new RoleResource($role))->response();
    }

    /**
     * POST /roles/{role}/users — assign role to a user in current company
     */
    public function assignToUser(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user    = User::findOrFail($data['user_id']);
        $company = Company::findOrFail(auth()->user()->current_company_id);

        try {
            $this->roleService->assignToUser($role, $user, $company);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Role assigned to user successfully.']);
    }

    /**
     * DELETE /roles/{role}/users/{user} — remove role from a user in current company
     */
    public function removeFromUser(Role $role, User $user): JsonResponse
    {
        $this->authorize('update', $role);

        $company = Company::findOrFail(auth()->user()->current_company_id);

        $this->roleService->removeFromUser($role, $user, $company);

        return response()->json(['message' => 'Role removed from user successfully.']);
    }
}
