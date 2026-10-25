<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Inventory\Concerns\ProvidesStockOptions;
use App\Models\Inventory\StockLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Current quantities per product and warehouse.
 */
class StockLevelController extends Controller
{
    use ProvidesStockOptions;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', StockLevel::class);

        $filters = $request->only(['search', 'warehouse_id', 'low']);

        $levels = StockLevel::query()
            ->select('stock_levels.*')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->whereNull('products.deleted_at')
            ->with(['product:id,code,name,unit,reorder_point,min_stock_level', 'warehouse:id,code,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query->whereLike('products.name', $like)->orWhereLike('products.code', $like));
            })
            ->when($filters['warehouse_id'] ?? null, fn ($query, $id) => $query->where('stock_levels.warehouse_id', $id))
            ->when($filters['low'] ?? null, fn ($query) => $query
                ->where('products.reorder_point', '>', 0)
                ->whereColumn('stock_levels.quantity_on_hand', '<=', 'products.reorder_point'))
            ->orderBy('products.name')
            ->paginate(20)
            ->withQueryString();

        $levels->through(fn (StockLevel $level) => [
            'id'        => $level->id,
            'product'   => $level->product?->only(['id', 'code', 'name', 'unit']),
            'warehouse' => $level->warehouse?->only(['id', 'code', 'name']),
            'on_hand'   => (float) $level->quantity_on_hand,
            'reserved'  => (float) $level->quantity_reserved,
            'available' => (float) $level->quantity_on_hand - (float) $level->quantity_reserved,
            'reorder_point' => (float) $level->product?->reorder_point,
            'low'       => (float) $level->product?->reorder_point > 0 && (float) $level->quantity_on_hand <= (float) $level->product->reorder_point,
            'last_movement_at' => $level->last_movement_at,
        ]);

        return Inertia::render('Inventory/Levels/Index', [
            'levels'     => $levels,
            'filters'    => $filters,
            'warehouses' => $this->warehouseOptions(),
        ]);
    }
}
