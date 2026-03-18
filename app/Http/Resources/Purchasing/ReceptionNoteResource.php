<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'reference'           => $this->reference,
            'purchase_order_id'   => $this->purchase_order_id,
            'purchase_order'      => $this->whenLoaded('purchaseOrder'),
            'supplier_id'         => $this->supplier_id,
            'supplier'            => $this->whenLoaded('supplier'),
            'status'              => $this->status,
            'reception_date'      => $this->reception_date?->toDateString(),
            'notes'               => $this->notes,
            'confirmed_by'        => $this->confirmed_by,
            'confirmed_at'        => $this->confirmed_at?->toISOString(),
            'cancelled_by'        => $this->cancelled_by,
            'cancelled_at'        => $this->cancelled_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,
            'lines'               => ReceptionNoteLineResource::collection($this->whenLoaded('lines')),
            'created_by'          => $this->created_by,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
