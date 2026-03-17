<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'company_id'              => $this->company_id,
            'personnel_id'            => $this->personnel_id,
            'reference'               => $this->reference,
            'type'                    => $this->type,
            'status'                  => $this->status,
            'start_date'              => $this->start_date?->toDateString(),
            'end_date'                => $this->end_date?->toDateString(),
            'trial_period_end_date'   => $this->trial_period_end_date?->toDateString(),
            'salary'                  => $this->salary,
            'salary_currency'         => $this->salary_currency,
            'working_hours_per_week'  => $this->working_hours_per_week,
            'benefits'                => $this->benefits,
            'document_path'           => $this->document_path,
            'notes'                   => $this->notes,
            'signed_at'               => $this->signed_at?->toISOString(),
            'terminated_at'           => $this->terminated_at?->toISOString(),
            'termination_reason'      => $this->termination_reason,
            'created_by'              => $this->created_by,
            'personnel'               => $this->whenLoaded('personnel', fn () => new PersonnelResource($this->personnel)),
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
