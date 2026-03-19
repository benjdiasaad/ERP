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
            'product'          => $this->whenLoaded('product'),
            'theoretical_qty'  => $this->theoretical_qty,
            'counted_qty'      => $this->counted_qty,
            'variance'         => $this->variance,
            'notes'            => $this->notes,
        ];
    }
}
