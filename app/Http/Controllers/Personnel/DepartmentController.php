<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreDepartmentRequest;
use App\Http\Requests\Personnel\UpdateDepartmentRequest;
use App\Services\Personnel\DepartmentService;
use App\Models\Personnel\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {}

    public function index(): JsonResponse
    {
        $departments = $this->departmentService->getTree();

        return response()->json($departments);
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        try {
            $department = $this->departmentService->create($request->validated());

            return response()->json($department, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['parent', 'manager', 'children']);

        return response()->json($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        try {
            $department = $this->departmentService->update($department, $request->validated());

            return response()->json($department);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Department $department): JsonResponse
    {
        try {
            $this->departmentService->delete($department);

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
