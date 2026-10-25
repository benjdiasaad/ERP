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
            'source_label'  => $this->source_type ? class_basename($this->source_type) : null,
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
