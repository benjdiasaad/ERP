<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
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

        $movement = $this->stockMovementService->createMovement($request->validated());

        return (new StockMovementResource($movement->load(['product', 'warehouse'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockMovement $stockMovement): StockMovementResource
    {
        $this->authorize('view', $stockMovement);

        $stockMovement->load(['product', 'warehouse']);

        return new StockMovementResource($stockMovement);
    }

    /**
     * Move stock between two warehouses of the company (one "out" and one "in" movement).
     */
    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $this->authorize('transfer', StockMovement::class);

        ['out_movement' => $out, 'in_movement' => $in] = $this->stockMovementService->transfer($request->validated());

        return response()->json([
            'out_movement' => new StockMovementResource($out),
            'in_movement'  => new StockMovementResource($in),
        ], 201);
    }

    /**
     * Quantities moved per type over a period (default: current month).
     */
    public function report(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $data = $request->validate([
            'from'         => ['nullable', 'date'],
            'to'           => ['nullable', 'date', 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $rows = StockMovement::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($data['warehouse_id'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->selectRaw('type, COUNT(*) as movements, SUM(ABS(quantity)) as quantity')
            ->groupBy('type')
            ->get();

        return response()->json(['data' => ['from' => $from, 'to' => $to, 'by_type' => $rows]]);
    }
}
