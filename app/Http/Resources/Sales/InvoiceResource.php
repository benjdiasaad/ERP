<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'reference'        => $this->reference,
            'customer_id'      => $this->customer_id,
            'customer'         => CustomerResource::make($this->whenLoaded('customer')),
            'sales_order_id'   => $this->sales_order_id,
            'sales_order'      => $this->whenLoaded('salesOrder'),
            'status'           => $this->status,
            'invoice_date'     => $this->invoice_date?->toDateString(),
            'due_date'         => $this->due_date?->toDateString(),
            'payment_term_id'  => $this->payment_term_id,
            'payment_term'     => $this->whenLoaded('paymentTerm'),
            'currency_id'      => $this->currency_id,
            'currency'         => $this->whenLoaded('currency'),
            'subtotal_ht'      => $this->subtotal_ht,
            'total_discount'   => $this->total_discount,
            'total_tax'        => $this->total_tax,
            'total_ttc'        => $this->total_ttc,
            'amount_paid'      => $this->amount_paid,
            'amount_due'       => $this->amount_due,
            'notes'            => $this->notes,
            'terms'            => $this->terms,
            'is_overdue'       => $this->isOverdue(),
            'is_fully_paid'    => $this->isFullyPaid(),
            'lines'            => InvoiceLineResource::collection($this->whenLoaded('lines')),
            'created_by'       => $this->created_by,
            'created_by_user'  => $this->whenLoaded('createdBy'),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
