<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditNoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'credit_note_id'   => $this->credit_note_id,
            'product_id'       => $this->product_id,
            'product'          => $this->whenLoaded('product'),
            'description'      => $this->description,
            'quantity'         => $this->quantity,
            'unit_price_ht'    => $this->unit_price_ht,
            'discount_type'    => $this->discount_type,
            'discount_value'   => $this->discount_value,
            'tax_rate'         => $this->tax_rate,
            'subtotal_ht'      => $this->subtotal_ht,
            'tax_amount'       => $this->tax_amount,
            'total_ttc'        => $this->total_ttc,
            'sort_order'       => $this->sort_order,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
