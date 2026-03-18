<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'delivery_note_id'    => $this->delivery_note_id,
            'sales_order_line_id' => $this->sales_order_line_id,
            'product_id'          => $this->product_id,
            'product'             => $this->whenLoaded('product'),
            'description'         => $this->description,
            'ordered_quantity'    => $this->ordered_quantity,
            'shipped_quantity'    => $this->shipped_quantity,
            'returned_quantity'   => $this->returned_quantity,
            'unit'                => $this->unit,
            'sort_order'          => $this->sort_order,
            'notes'               => $this->notes,
        ];
    }
}
