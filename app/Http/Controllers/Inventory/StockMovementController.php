<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Http\Requests\Inventory\UpdateStockMovementRequest;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\StockMovement;
use App\Services\Inventory\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        $movements = StockMovement::with(['product', 'warehouse'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->product_id, fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return StockMovementResource::collection($movements);
    }

    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $this->authorize('create', StockMovement::class);

        $movement = $this->stockMovementService->create($request->validated());

        return (new StockMovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockMovement $stockMovement): StockMovementResource
    {
        $this->authorize('view', $stockMovement);

        $stockMovement->load(['product', 'warehouse']);

        return new StockMovementResource($stockMovement);
    }

    public function update(UpdateStockMovementRequest $request, StockMovement $stockMovement): StockMovementResource
    {
        $this->authorize('update', $stockMovement);

        $movement = $this->stockMovementService->update($stockMovement, $request->validated());

        return new StockMovementResource($movement);
    }

    public function destroy(StockMovement $stockMovement): JsonResponse
    {
        $this->authorize('delete', $stockMovement);

        $this->stockMovementService->delete($stockMovement);

        return response()->json(null, 204);
    }
}
