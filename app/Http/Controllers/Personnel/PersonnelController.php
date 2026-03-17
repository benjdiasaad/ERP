<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StorePersonnelRequest;
use App\Http\Requests\Personnel\UpdatePersonnelRequest;
use App\Services\Personnel\PersonnelService;
use App\Models\Personnel\Personnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    public function __construct(
        private readonly PersonnelService $personnelService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['name', 'department_id', 'position_id', 'status', 'employment_type', 'per_page']);

        $personnel = $this->personnelService->search($filters);

        return response()->json($personnel);
    }

    public function store(StorePersonnelRequest $request): JsonResponse
    {
        try {
            $personnel = $this->personnelService->create($request->validated());

            return response()->json($personnel, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Personnel $personnel): JsonResponse
    {
        $personnel->load(['department', 'position', 'user']);

        return response()->json($personnel);
    }

    public function update(UpdatePersonnelRequest $request, Personnel $personnel): JsonResponse
    {
        try {
            $personnel = $this->personnelService->update($personnel, $request->validated());

            return response()->json($personnel);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Personnel $personnel): JsonResponse
    {
        try {
            $this->personnelService->delete($personnel);

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
