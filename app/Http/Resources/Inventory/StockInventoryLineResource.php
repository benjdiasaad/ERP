<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockInventoryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'stock_inventory_id' => $this->stock_inventory_id,
            'product_id'       => $this->product_id,
            'product'          => $this->whenLoaded('product', fn () => $this->product?->only(['id', 'code', 'name', 'unit'])),
            'theoretical_quantity' => $this->theoretical_quantity,
            'counted_quantity' => $this->counted_quantity,
            'variance'         => $this->variance,
            'variance_percentage' => $this->variance_percentage,
            'notes'            => $this->notes,
        ];
    }
}
