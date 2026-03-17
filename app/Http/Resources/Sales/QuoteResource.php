<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'reference'            => $this->reference,
            'customer_id'          => $this->customer_id,
            'customer'             => CustomerResource::make($this->whenLoaded('customer')),
            'quote_date'           => $this->quote_date?->toDateString(),
            'validity_date'        => $this->validity_date?->toDateString(),
            'status'               => $this->status,
            'subtotal_ht'          => $this->subtotal_ht,
            'total_discount'       => $this->total_discount,
            'total_tax'            => $this->total_tax,
            'total_ttc'            => $this->total_ttc,
            'currency_id'          => $this->currency_id,
            'currency'             => $this->whenLoaded('currency'),
            'payment_term_id'      => $this->payment_term_id,
            'payment_term'         => $this->whenLoaded('paymentTerm'),
            'notes'                => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,
            'converted_to_order_id' => $this->converted_to_order_id,
            'sent_at'              => $this->sent_at?->toISOString(),
            'accepted_at'          => $this->accepted_at?->toISOString(),
            'rejected_at'          => $this->rejected_at?->toISOString(),
            'rejection_reason'     => $this->rejection_reason,
            'lines'                => QuoteLineResource::collection($this->whenLoaded('lines')),
            'created_by'           => $this->created_by,
            'updated_by'           => $this->updated_by,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
