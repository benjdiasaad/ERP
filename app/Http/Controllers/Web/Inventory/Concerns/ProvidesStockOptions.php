<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory\Concerns;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Collection;

/**
 * Select options shared by the stock screens (current company only).
 */
trait ProvidesStockOptions
{
    protected function warehouseOptions(): Collection
    {
        return Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn (Warehouse $w) => ['value' => $w->id, 'label' => "{$w->name} ({$w->code})"]);
    }

    protected function stockableProductOptions(): Collection
    {
        return Product::where('is_active', true)->where('is_stockable', true)->orderBy('name')->get(['id', 'code', 'name', 'unit'])
            ->map(fn (Product $p) => ['value' => $p->id, 'label' => "{$p->name} ({$p->code})", 'unit' => $p->unit]);
    }
}
