<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockInventoryRequest;
use App\Http\Requests\Inventory\UpdateStockInventoryRequest;
use App\Http\Resources\Inventory\StockInventoryResource;
use App\Models\Inventory\StockInventory;
use App\Services\Inventory\StockInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class StockInventoryController extends Controller
{
    public function __construct(
        private readonly StockInventoryService $stockInventoryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockInventory::class);

        $inventories = StockInventory::with(['warehouse', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return StockInventoryResource::collection($inventories);
    }

    public function store(StoreStockInventoryRequest $request): JsonResponse
    {
        $this->authorize('create', StockInventory::class);

        $inventory = $this->stockInventoryService->create($request->validated());

        return (new StockInventoryResource($inventory))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockInventory $stockInventory): StockInventoryResource
    {
        $this->authorize('view', $stockInventory);

        $stockInventory->load(['warehouse', 'lines']);

        return new StockInventoryResource($stockInventory);
    }

    public function update(UpdateStockInventoryRequest $request, StockInventory $stockInventory): StockInventoryResource
    {
        $this->authorize('update', $stockInventory);

        $inventory = $this->stockInventoryService->update($stockInventory, $request->validated());

        return new StockInventoryResource($inventory);
    }

    public function destroy(StockInventory $stockInventory): JsonResponse
    {
        $this->authorize('delete', $stockInventory);

        $this->stockInventoryService->delete($stockInventory);

        return response()->json(null, 204);
    }

    public function start(StockInventory $stockInventory): StockInventoryResource
    {
        $this->authorize('start', $stockInventory);

        return new StockInventoryResource($this->stockInventoryService->start($stockInventory)->load(['lines.product', 'warehouse']));
    }

    /**
     * Apply the counted quantities: one adjustment movement per line with a variance.
     */
    public function validate(StockInventory $stockInventory): StockInventoryResource
    {
        $this->authorize('validate', $stockInventory);

        return new StockInventoryResource($this->stockInventoryService->validate($stockInventory)->load(['lines.product', 'warehouse']));
    }
}
