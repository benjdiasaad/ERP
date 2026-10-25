<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Inventory\Concerns\ProvidesStockOptions;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Http\Requests\Inventory\TransferStockRequest;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\StockMovement;
use App\Services\Inventory\StockMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The stock ledger: movements are never edited or deleted, only added.
 */
class StockMovementController extends Controller
{
    use ProvidesStockOptions;

    public function __construct(
        private readonly StockMovementService $movementService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        $filters = $request->only(['search', 'warehouse_id', 'type', 'from', 'to']);

        $movements = StockMovement::query()
            ->with(['product:id,code,name,unit', 'warehouse:id,code,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('product', fn ($query) => $query->whereLike('name', $like)->orWhereLike('code', $like)));
            })
            ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Inventory/Movements/Index', [
            'movements'  => StockMovementResource::collection($movements),
            'filters'    => $filters,
            'warehouses' => $this->warehouseOptions(),
            'products'   => $this->stockableProductOptions(),
        ]);
    }

    public function store(StoreStockMovementRequest $request): RedirectResponse
    {
        $this->authorize('create', StockMovement::class);

        $this->movementService->createMovement($request->validated());

        return back()->with('success', 'Mouvement enregistré.');
    }

    public function transfer(TransferStockRequest $request): RedirectResponse
    {
        $this->authorize('transfer', StockMovement::class);

        $this->movementService->transfer($request->validated());

        return back()->with('success', 'Transfert enregistré.');
    }
}
