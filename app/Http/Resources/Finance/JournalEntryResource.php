<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'reference'     => $this->reference,
            'date'          => $this->date?->toDateString(),
            'description'   => $this->description,
            'status'        => $this->status,
            'total_debit'   => $this->total_debit,
            'total_credit'  => $this->total_credit,
            'lines'         => JournalEntryLineResource::collection($this->whenLoaded('lines')),
            'posted_at'     => $this->posted_at?->toISOString(),
            'posted_by'     => $this->whenLoaded('postedBy', fn () => [
                'id'   => $this->postedBy->id,
                'name' => $this->postedBy->first_name . ' ' . $this->postedBy->last_name,
            ]),
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
