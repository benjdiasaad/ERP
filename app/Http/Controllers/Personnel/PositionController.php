<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StorePositionRequest;
use App\Http\Requests\Personnel\UpdatePositionRequest;
use App\Services\Personnel\PositionService;
use App\Models\Personnel\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function __construct(
        private readonly PositionService $positionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->filled('department_id')) {
            $positions = $this->positionService->getByDepartment((int) $request->input('department_id'));
        } else {
            $positions = Position::with('department')->get();
        }

        return response()->json($positions);
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        try {
            $position = $this->positionService->create($request->validated());

            return response()->json($position, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Position $position): JsonResponse
    {
        $position->load(['department']);

        return response()->json($position);
    }

    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        try {
            $position = $this->positionService->update($position, $request->validated());

            return response()->json($position);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Position $position): JsonResponse
    {
        try {
            $this->positionService->delete($position);

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
