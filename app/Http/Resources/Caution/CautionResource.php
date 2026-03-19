<?php

declare(strict_types=1);

namespace App\Http\Resources\Caution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CautionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'caution_type_id'   => $this->caution_type_id,
            'caution_type'      => new CautionTypeResource($this->whenLoaded('cautionType')),
            'direction'         => $this->direction,
            'partner_type'      => $this->partner_type,
            'partner_id'        => $this->partner_id,
            'related_type'      => $this->related_type,
            'related_id'        => $this->related_id,
            'amount'            => $this->amount,
            'currency'          => $this->currency,
            'issue_date'        => $this->issue_date?->toDateString(),
            'expiry_date'       => $this->expiry_date?->toDateString(),
            'return_date'       => $this->return_date?->toDateString(),
            'amount_returned'   => $this->amount_returned,
            'amount_forfeited'  => $this->amount_forfeited,
            'bank_name'         => $this->bank_name,
            'bank_account'      => $this->bank_account,
            'bank_reference'    => $this->bank_reference,
            'document_reference' => $this->document_reference,
            'status'            => $this->status,
            'notes'             => $this->notes,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
