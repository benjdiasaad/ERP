<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Inventory\Concerns\ProvidesStockOptions;
use App\Http\Requests\Inventory\StoreStockInventoryRequest;
use App\Http\Requests\Inventory\UpdateStockInventoryRequest;
use App\Http\Resources\Inventory\StockInventoryResource;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockInventory;
use App\Services\Inventory\StockInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Physical counts: draft (choose products) → in progress (enter counts) → validated (stock adjusted).
 */
class StockInventoryController extends Controller
{
    use ProvidesStockOptions;

    public function __construct(
        private readonly StockInventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockInventory::class);

        $filters = $request->only(['search', 'status', 'warehouse_id']);

        $inventories = StockInventory::query()
            ->with('warehouse:id,code,name')
            ->withCount('lines')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereLike('reference', '%' . $search . '%'))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('warehouse_id', $id))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Inventory/Inventories/Index', [
            'inventories' => StockInventoryResource::collection($inventories),
            'counts'      => StockInventory::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters'     => $filters,
            'warehouses'  => $this->warehouseOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', StockInventory::class);

        return Inertia::render('Inventory/Inventories/Create', ['warehouses' => $this->warehouseOptions()]);
    }

    public function store(StoreStockInventoryRequest $request): RedirectResponse
    {
        $this->authorize('create', StockInventory::class);

        $data = $request->validated();
        // By default the sheet lists every active stockable product.
        if (empty($data['lines']) && $request->boolean('all_products', true)) {
            $data['lines'] = Product::where('is_active', true)->where('is_stockable', true)->orderBy('name')->pluck('id')
                ->map(fn (int $id) => ['product_id' => $id])->all();
        }

        $inventory = $this->inventoryService->create($data);

        return redirect()->route('stock.inventories.show', $inventory)->with('success', "Inventaire {$inventory->reference} créé.");
    }

    public function show(StockInventory $inventory): Response
    {
        $this->authorize('view', $inventory);

        $inventory->load(['warehouse:id,code,name', 'lines.product:id,code,name,unit']);

        return Inertia::render('Inventory/Inventories/Show', [
            'inventory' => new StockInventoryResource($inventory),
            'products'  => $inventory->status === 'draft' ? $this->stockableProductOptions() : [],
        ]);
    }

    /**
     * Draft: products on the sheet. In progress: counted quantities.
     */
    public function update(UpdateStockInventoryRequest $request, StockInventory $inventory): RedirectResponse
    {
        $this->authorize('update', $inventory);

        if ($inventory->status === 'in_progress') {
            $this->inventoryService->recordCounts($inventory, $request->validated('lines', []));

            return back()->with('success', 'Comptages enregistrés.');
        }

        $this->inventoryService->update($inventory, $request->validated());

        return back()->with('success', 'Inventaire mis à jour.');
    }

    public function destroy(StockInventory $inventory): RedirectResponse
    {
        $this->authorize('delete', $inventory);

        $this->inventoryService->delete($inventory);

        return redirect()->route('stock.inventories.index')->with('success', 'Inventaire supprimé.');
    }

    public function start(StockInventory $inventory): RedirectResponse
    {
        $this->authorize('start', $inventory);

        $this->inventoryService->start($inventory);

        return back()->with('success', 'Comptage démarré : saisissez les quantités comptées.');
    }

    public function finalize(StockInventory $inventory): RedirectResponse
    {
        $this->authorize('validate', $inventory);

        $this->inventoryService->validate($inventory);

        return back()->with('success', 'Inventaire validé : le stock a été ajusté.');
    }

    public function cancel(Request $request, StockInventory $inventory): RedirectResponse
    {
        $this->authorize('cancel', $inventory);

        $this->inventoryService->cancel($inventory, $request->string('reason')->toString() ?: null);

        return back()->with('success', 'Inventaire annulé.');
    }
}
