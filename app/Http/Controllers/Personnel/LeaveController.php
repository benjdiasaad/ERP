<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreLeaveRequest;
use App\Http\Requests\Personnel\UpdateLeaveRequest;
use App\Services\Personnel\LeaveService;
use App\Models\Personnel\Leave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Leave::query()->with(['personnel', 'approvedBy']);

        if ($request->filled('personnel_id')) {
            $query->where('personnel_id', $request->input('personnel_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('leave_type', $request->input('type'));
        }

        $leaves = $query->orderByDesc('start_date')->paginate(15);

        return response()->json($leaves);
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        try {
            $leave = $this->leaveService->create($request->validated());

            return response()->json($leave, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Leave $leave): JsonResponse
    {
        $leave->load(['personnel', 'approvedBy']);

        return response()->json($leave);
    }

    public function update(UpdateLeaveRequest $request, Leave $leave): JsonResponse
    {
        try {
            $leave = $this->leaveService->update($leave, $request->validated());

            return response()->json($leave);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Leave $leave): JsonResponse
    {
        try {
            $leave->delete();

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function approve(Request $request, Leave $leave): JsonResponse
    {
        try {
            $leave = $this->leaveService->approve($leave, auth()->id());

            return response()->json($leave);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function reject(Request $request, Leave $leave): JsonResponse
    {
        try {
            $reason = $request->input('reason', '');
            $leave  = $this->leaveService->reject($leave, auth()->id(), $reason);

            return response()->json($leave);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
