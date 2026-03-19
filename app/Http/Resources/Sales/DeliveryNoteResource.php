<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'reference'              => $this->reference,
            'customer_id'            => $this->customer_id,
            'customer'               => CustomerResource::make($this->whenLoaded('customer')),
            'sales_order_id'         => $this->sales_order_id,
            'sales_order'            => $this->whenLoaded('salesOrder'),
            'status'                 => $this->status,
            'date'                   => $this->date?->toDateString(),
            'expected_delivery_date' => $this->expected_delivery_date?->toDateString(),
            'delivery_address'       => $this->delivery_address,
            'carrier'                => $this->carrier,
            'tracking_number'        => $this->tracking_number,
            'notes'                  => $this->notes,
            'lines'                  => DeliveryNoteLineResource::collection($this->whenLoaded('lines')),
            'created_by'             => $this->created_by,
            'created_by_user'        => $this->whenLoaded('createdBy'),
            'shipped_by'             => $this->shipped_by,
            'shipped_by_user'        => $this->whenLoaded('shippedBy'),
            'delivered_by'           => $this->delivered_by,
            'delivered_by_user'      => $this->whenLoaded('deliveredBy'),
            'shipped_at'             => $this->shipped_at?->toDateString(),
            'delivered_at'           => $this->delivered_at?->toDateString(),
            'created_at'             => $this->created_at?->toISOString(),
            'updated_at'             => $this->updated_at?->toISOString(),
        ];
    }
}
