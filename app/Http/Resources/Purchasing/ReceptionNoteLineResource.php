<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceptionNoteLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'product_id'             => $this->product_id,
            'description'            => $this->description,
            'ordered_quantity'       => $this->ordered_quantity,
            'received_quantity'      => $this->received_quantity,
            'rejected_quantity'      => $this->rejected_quantity,
            'unit'                   => $this->unit,
            'notes'                  => $this->notes,
            'sort_order'             => $this->sort_order,
        ];
    }
}
