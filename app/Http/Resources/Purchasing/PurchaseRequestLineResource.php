<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'purchase_request_id'   => $this->purchase_request_id,
            'product_id'            => $this->product_id,
            'description'           => $this->description,
            'quantity'              => $this->quantity,
            'unit'                  => $this->unit,
            'estimated_unit_price'  => $this->estimated_unit_price,
            'estimated_total'       => $this->estimated_total,
            'notes'                 => $this->notes,
            'sort_order'            => $this->sort_order,
        ];
    }
}
