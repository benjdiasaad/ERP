<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'bank'           => $this->bank,
            'account_number' => $this->account_number,
            'iban'           => $this->iban,
            'swift'          => $this->swift,
            'currency_id'    => $this->currency_id,
            'currency'       => new CurrencyResource($this->whenLoaded('currency')),
            'balance'        => (float) $this->balance,
            'is_active'      => $this->is_active,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
