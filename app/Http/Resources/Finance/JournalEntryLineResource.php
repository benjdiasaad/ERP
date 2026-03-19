<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'journal_entry_id'      => $this->journal_entry_id,
            'chart_of_account_id'   => $this->chart_of_account_id,
            'chart_of_account'      => new ChartOfAccountResource($this->whenLoaded('chartOfAccount')),
            'debit'                 => $this->debit,
            'credit'                => $this->credit,
            'description'           => $this->description,
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
