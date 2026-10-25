<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Http\Requests\Inventory\UpdateWarehouseRequest;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Http\Resources\Inventory\WarehouseResource;
use App\Models\Inventory\Warehouse;
use App\Models\Personnel\Personnel;
use App\Services\Inventory\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);

        $filters = $request->only(['search', 'status']);

        $warehouses = Warehouse::query()
            ->withCount(['stockLevels as products_count' => fn ($query) => $query->where('quantity_on_hand', '>', 0)])
            ->withSum('stockLevels as units_on_hand', 'quantity_on_hand')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('name', $like)->orWhereLike('code', $like)->orWhereLike('city', $like));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Before the resource collection wraps the models.
        $totals = $warehouses->getCollection()->mapWithKeys(fn (Warehouse $w) => [$w->id => [
            'products' => (int) $w->products_count, 'units' => (float) ($w->units_on_hand ?? 0),
        ]]);

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => WarehouseResource::collection($warehouses),
            'totals'     => $totals,
            'filters'    => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Warehouse::class);

        return Inertia::render('Inventory/Warehouses/Create', $this->formOptions());
    }

    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create($request->validated());

        return redirect()->route('stock.warehouses.show', $warehouse)->with('success', "Entrepôt {$warehouse->code} créé.");
    }

    public function show(Warehouse $warehouse): Response
    {
        $this->authorize('view', $warehouse);

        $warehouse->load('manager:id,first_name,last_name');
        $user = auth()->user();

        return Inertia::render('Inventory/Warehouses/Show', [
            'warehouse' => new WarehouseResource($warehouse),
            'levels'    => $warehouse->stockLevels()->with('product:id,code,name,unit,reorder_point')
                ->where('quantity_on_hand', '!=', 0)->get()
                ->sortBy(fn ($level) => $level->product?->name)->values()
                ->map(fn ($level) => [
                    'product'  => $level->product?->only(['id', 'code', 'name', 'unit']),
                    'on_hand'  => (float) $level->quantity_on_hand,
                    'reserved' => (float) $level->quantity_reserved,
                    'low'      => (float) $level->product?->reorder_point > 0 && (float) $level->quantity_on_hand <= (float) $level->product->reorder_point,
                ]),
            'movements' => $user->hasPermission('stock_movements.view_any')
                ? StockMovementResource::collection($warehouse->stockMovements()->with('product:id,code,name,unit')->latest('id')->limit(10)->get())
                : null,
        ]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        $this->authorize('update', $warehouse);

        return Inertia::render('Inventory/Warehouses/Edit', [
            'warehouse' => new WarehouseResource($warehouse),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);

        $this->warehouseService->update($warehouse, $request->validated());

        return redirect()->route('stock.warehouses.show', $warehouse)->with('success', 'Entrepôt mis à jour.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse);

        return redirect()->route('stock.warehouses.index')->with('success', 'Entrepôt supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'managers' => Personnel::orderBy('last_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn (Personnel $p) => ['value' => $p->id, 'label' => trim("{$p->first_name} {$p->last_name}")]),
        ];
    }
}
