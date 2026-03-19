<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\StockLevelResource;
use App\Models\Inventory\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockLevelController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockLevel::class);

        $levels = StockLevel::with(['product', 'warehouse'])
            ->when($request->product_id, fn ($q) => $q->where('product_id', $request->product_id))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->low_stock, fn ($q) => $q->whereRaw('quantity_available <= (select reorder_point from products where id = product_id)'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return StockLevelResource::collection($levels);
    }

    public function show(StockLevel $stockLevel): StockLevelResource
    {
        $this->authorize('view', $stockLevel);

        $stockLevel->load(['product', 'warehouse']);

        return new StockLevelResource($stockLevel);
    }
}
