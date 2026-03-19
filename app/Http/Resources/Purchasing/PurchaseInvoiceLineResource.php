<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseInvoiceLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'purchase_invoice_id'        => $this->purchase_invoice_id,
            'product_id'                 => $this->product_id,
            'description'                => $this->description,
            'quantity'                   => $this->quantity,
            'unit'                       => $this->unit,
            'unit_price_ht'              => $this->unit_price_ht,
            'discount_type'              => $this->discount_type,
            'discount_value'             => $this->discount_value,
            'subtotal_ht'                => $this->subtotal_ht,
            'discount_amount'            => $this->discount_amount,
            'subtotal_ht_after_discount' => $this->subtotal_ht_after_discount,
            'tax_id'                     => $this->tax_id,
            'tax_rate'                   => $this->tax_rate,
            'tax_amount'                 => $this->tax_amount,
            'total_ttc'                  => $this->total_ttc,
            'sort_order'                 => $this->sort_order,
        ];
    }
}
