<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreAttendanceRequest;
use App\Http\Requests\Personnel\UpdateAttendanceRequest;
use App\Services\Personnel\AttendanceService;
use App\Models\Personnel\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Attendance::query()->with(['personnel']);

        if ($request->filled('personnel_id')) {
            $query->where('personnel_id', $request->input('personnel_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $attendances = $query->orderByDesc('date')->paginate(15);

        return response()->json($attendances);
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        try {
            $attendance = $this->attendanceService->create($request->validated());

            return response()->json($attendance, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Attendance $attendance): JsonResponse
    {
        $attendance->load(['personnel']);

        return response()->json($attendance);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        try {
            $attendance = $this->attendanceService->update($attendance, $request->validated());

            return response()->json($attendance);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        try {
            $attendance->delete();

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
