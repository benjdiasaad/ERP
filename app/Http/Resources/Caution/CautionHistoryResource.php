<?php

declare(strict_types=1);

namespace App\Http\Resources\Caution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CautionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'caution_id'        => $this->caution_id,
            'action'            => $this->action,
            'amount'            => $this->amount,
            'previous_status'   => $this->previous_status,
            'new_status'        => $this->new_status,
            'notes'             => $this->notes,
            'created_by'        => $this->created_by,
            'created_at'        => $this->created_at?->toISOString(),
            'updated_at'        => $this->updated_at?->toISOString(),
        ];
    }
}
