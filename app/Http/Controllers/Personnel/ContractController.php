<?php

declare(strict_types=1);

namespace App\Http\Controllers\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreContractRequest;
use App\Http\Requests\Personnel\UpdateContractRequest;
use App\Services\Personnel\ContractService;
use App\Models\Personnel\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Contract::query()->with(['personnel']);

        if ($request->filled('personnel_id')) {
            $query->where('personnel_id', $request->input('personnel_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $contracts = $query->orderByDesc('start_date')->paginate(15);

        return response()->json($contracts);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            $contract = $this->contractService->create($request->validated());

            return response()->json($contract, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['personnel']);

        return response()->json($contract);
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        try {
            $contract = $this->contractService->update($contract, $request->validated());

            return response()->json($contract);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }

    public function destroy(Contract $contract): JsonResponse
    {
        try {
            $contract->delete();

            return response()->noContent();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
