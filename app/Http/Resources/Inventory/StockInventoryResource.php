<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'warehouse_id'   => $this->warehouse_id,
            'warehouse'      => $this->whenLoaded('warehouse'),
            'reference'      => $this->reference,
            'inventory_date' => $this->inventory_date?->toDateString(),
            'status'         => $this->status,
            'notes'          => $this->notes,
            'lines'          => StockInventoryLineResource::collection($this->whenLoaded('lines')),
            'created_by'     => $this->created_by,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
