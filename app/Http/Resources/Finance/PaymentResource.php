<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'reference'         => $this->reference,
            'payable_type'      => $this->payable_type,
            'payable_id'        => $this->payable_id,
            'payable'           => $this->when($this->relationLoaded('payable'), $this->payable),
            'direction'         => $this->direction,
            'amount'            => $this->amount,
            'payment_method_id' => $this->payment_method_id,
            'payment_method'    => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'bank_account_id'   => $this->bank_account_id,
            'bank_account'      => new BankAccountResource($this->whenLoaded('bankAccount')),
            'payment_date'      => $this->payment_date?->toDateString(),
            'status'            => $this->status,
            'notes'             => $this->notes,
            'confirmed_at'      => $this->confirmed_at?->toISOString(),
            'confirmed_by'      => $this->whenLoaded('confirmedBy', fn () => [
                'id'   => $this->confirmedBy->id,
                'name' => $this->confirmedBy->first_name . ' ' . $this->confirmedBy->last_name,
            ]),
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
