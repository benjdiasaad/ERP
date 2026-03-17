<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'type'             => $this->type,
            'name'             => $this->name,
            'first_name'       => $this->first_name,
            'last_name'        => $this->last_name,
            'email'            => $this->email,
            'phone'            => $this->phone,
            'mobile'           => $this->mobile,
            'address'          => $this->address,
            'city'             => $this->city,
            'state'            => $this->state,
            'country'          => $this->country,
            'postal_code'      => $this->postal_code,
            'tax_id'           => $this->tax_id,
            'ice'              => $this->ice,
            'rc'               => $this->rc,
            'payment_terms_id' => $this->payment_terms_id,
            'payment_term'     => $this->whenLoaded('paymentTerm'),
            'credit_limit'     => $this->credit_limit,
            'balance'          => $this->balance,
            'currency_id'      => $this->currency_id,
            'currency'         => $this->whenLoaded('currency'),
            'notes'            => $this->notes,
            'is_active'        => $this->is_active,
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
