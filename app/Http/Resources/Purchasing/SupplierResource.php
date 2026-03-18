<?php

declare(strict_types=1);

namespace App\Http\Resources\Purchasing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'type'                => $this->type,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'mobile'              => $this->mobile,
            'address'             => $this->address,
            'city'                => $this->city,
            'state'               => $this->state,
            'country'             => $this->country,
            'postal_code'         => $this->postal_code,
            'tax_id'              => $this->tax_id,
            'ice'                 => $this->ice,
            'rc'                  => $this->rc,
            'payment_term_id'     => $this->payment_term_id,
            'payment_term'        => $this->whenLoaded('paymentTerm'),
            'credit_limit'        => $this->credit_limit,
            'balance'             => $this->balance,
            'bank_name'           => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_iban'           => $this->bank_iban,
            'bank_swift'          => $this->bank_swift,
            'notes'               => $this->notes,
            'is_active'           => $this->is_active,
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
