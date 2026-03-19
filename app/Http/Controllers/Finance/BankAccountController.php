<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Http\Requests\Finance\UpdateBankAccountRequest;
use App\Http\Resources\Finance\BankAccountResource;
use App\Models\Finance\BankAccount;
use App\Services\Finance\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly BankAccountService $bankAccountService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankAccount::class);

        $filters = $request->only(['search', 'currency_id', 'is_active', 'sort', 'per_page']);
        $accounts = $this->bankAccountService->search($filters);

        return BankAccountResource::collection($accounts);
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $this->authorize('create', BankAccount::class);

        $account = $this->bankAccountService->create($request->validated());

        return (new BankAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BankAccount $account): BankAccountResource
    {
        $this->authorize('view', $account);

        return new BankAccountResource($account);
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $account): BankAccountResource
    {
        $this->authorize('update', $account);

        $account = $this->bankAccountService->update($account, $request->validated());

        return new BankAccountResource($account);
    }

    public function destroy(BankAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->bankAccountService->delete($account);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }
}
