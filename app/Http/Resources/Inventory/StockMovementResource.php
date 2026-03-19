<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'product'       => $this->whenLoaded('product'),
            'warehouse_id'  => $this->warehouse_id,
            'warehouse'     => $this->whenLoaded('warehouse'),
            'type'          => $this->type,
            'quantity'      => $this->quantity,
            'reference'     => $this->reference,
            'source_type'   => $this->source_type,
            'source_id'     => $this->source_id,
            'notes'         => $this->notes,
            'movement_date' => $this->movement_date?->toDateString(),
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
