<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'reference'               => $this->reference,
            'customer_id'             => $this->customer_id,
            'customer'                => CustomerResource::make($this->whenLoaded('customer')),
            'quote_id'                => $this->quote_id,
            'status'                  => $this->status,
            'order_date'              => $this->order_date?->toDateString(),
            'expected_delivery_date'  => $this->expected_delivery_date?->toDateString(),
            'delivery_address'        => $this->delivery_address,
            'currency_id'             => $this->currency_id,
            'currency'                => $this->whenLoaded('currency'),
            'payment_term_id'         => $this->payment_term_id,
            'payment_term'            => $this->whenLoaded('paymentTerm'),
            'subtotal_ht'             => $this->subtotal_ht,
            'total_discount'          => $this->total_discount,
            'total_tax'               => $this->total_tax,
            'total_ttc'               => $this->total_ttc,
            'amount_invoiced'         => $this->amount_invoiced,
            'notes'                   => $this->notes,
            'terms_conditions'        => $this->terms_conditions,
            'confirmed_at'            => $this->confirmed_at?->toISOString(),
            'cancelled_at'            => $this->cancelled_at?->toISOString(),
            'cancellation_reason'     => $this->cancellation_reason,
            'lines'                   => SalesOrderLineResource::collection($this->whenLoaded('lines')),
            'created_by'              => $this->created_by,
            'created_by_user'         => $this->whenLoaded('createdBy'),
            'confirmed_by'            => $this->confirmed_by,
            'confirmed_by_user'       => $this->whenLoaded('confirmedBy'),
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
