<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreChartOfAccountRequest;
use App\Http\Requests\Finance\UpdateChartOfAccountRequest;
use App\Http\Resources\Finance\ChartOfAccountResource;
use App\Models\Finance\ChartOfAccount;
use App\Services\Finance\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ChartOfAccountController extends Controller
{
    public function __construct(
        private readonly ChartOfAccountService $chartOfAccountService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $filters = $request->only(['search', 'type', 'is_active', 'sort', 'per_page']);
        $accounts = $this->chartOfAccountService->search($filters);

        return ChartOfAccountResource::collection($accounts);
    }

    public function store(StoreChartOfAccountRequest $request): JsonResponse
    {
        $this->authorize('create', ChartOfAccount::class);

        $account = $this->chartOfAccountService->create($request->validated());

        return (new ChartOfAccountResource($account))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ChartOfAccount $account): ChartOfAccountResource
    {
        $this->authorize('view', $account);

        return new ChartOfAccountResource($account);
    }

    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $account): ChartOfAccountResource
    {
        $this->authorize('update', $account);

        $account = $this->chartOfAccountService->update($account, $request->validated());

        return new ChartOfAccountResource($account);
    }

    public function destroy(ChartOfAccount $account): JsonResponse
    {
        $this->authorize('delete', $account);

        try {
            $this->chartOfAccountService->delete($account);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Get the hierarchical tree of chart of accounts.
     */
    public function tree(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ChartOfAccount::class);

        $tree = $this->chartOfAccountService->getTree();

        return ChartOfAccountResource::collection($tree);
    }

    /**
     * Get the balance of a specific account.
     */
    public function balance(ChartOfAccount $account): JsonResponse
    {
        $this->authorize('view', $account);

        $balance = $this->chartOfAccountService->calculateHierarchyBalance($account);

        return response()->json([
            'account_id'         => $account->id,
            'code'               => $account->code,
            'name'               => $account->name,
            'direct_balance'     => (float) $account->balance,
            'hierarchy_balance'  => $balance,
        ]);
    }
}
