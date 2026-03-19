<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'reference'        => $this->reference,
            'customer_id'      => $this->customer_id,
            'customer'         => CustomerResource::make($this->whenLoaded('customer')),
            'invoice_id'       => $this->invoice_id,
            'invoice'          => $this->whenLoaded('invoice'),
            'status'           => $this->status,
            'date'             => $this->date?->toDateString(),
            'reason'           => $this->reason,
            'subtotal_ht'      => $this->subtotal_ht,
            'total_discount'   => $this->total_discount,
            'total_tax'        => $this->total_tax,
            'total_ttc'        => $this->total_ttc,
            'notes'            => $this->notes,
            'lines'            => CreditNoteLineResource::collection($this->whenLoaded('lines')),
            'created_by'       => $this->created_by,
            'created_by_user'  => $this->whenLoaded('createdBy'),
            'confirmed_at'     => $this->confirmed_at?->toISOString(),
            'applied_at'       => $this->applied_at?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
