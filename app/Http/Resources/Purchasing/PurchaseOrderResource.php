<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'reference'              => $this->reference,
            'supplier_id'            => $this->supplier_id,
            'supplier'               => $this->whenLoaded('supplier'),
            'purchase_request_id'    => $this->purchase_request_id,
            'purchase_request'       => $this->whenLoaded('purchaseRequest'),
            'status'                 => $this->status,
            'order_date'             => $this->order_date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'delivery_address'       => $this->delivery_address,
            'payment_term_id'        => $this->payment_term_id,
            'currency_id'            => $this->currency_id,
            'subtotal_ht'            => $this->subtotal_ht,
            'total_discount'         => $this->total_discount,
            'total_tax'              => $this->total_tax,
            'total_ttc'              => $this->total_ttc,
            'amount_received'        => $this->amount_received,
            'amount_invoiced'        => $this->amount_invoiced,
            'notes'                  => $this->notes,
            'terms_conditions'       => $this->terms_conditions,
            'confirmed_by'           => $this->confirmed_by,
            'confirmed_at'           => $this->confirmed_at?->toISOString(),
            'sent_at'                => $this->sent_at?->toISOString(),
            'cancelled_by'           => $this->cancelled_by,
            'cancelled_at'           => $this->cancelled_at?->toISOString(),
            'cancellation_reason'    => $this->cancellation_reason,
            'lines'                  => PurchaseOrderLineResource::collection($this->whenLoaded('lines')),
            'created_by'             => $this->created_by,
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }
}
