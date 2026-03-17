<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'company_id'       => $this->company_id,
            'personnel_id'     => $this->personnel_id,
            'leave_type'       => $this->leave_type,
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
            'total_days'       => $this->total_days,
            'reason'           => $this->reason,
            'status'           => $this->status,
            'approved_by'      => $this->approved_by,
            'approved_at'      => $this->approved_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'notes'            => $this->notes,
            'personnel'        => $this->whenLoaded('personnel', fn () => new PersonnelResource($this->personnel)),
            'approvedBy'       => $this->whenLoaded('approvedBy', fn () => [
                'id'         => $this->approvedBy->id,
                'first_name' => $this->approvedBy->first_name,
                'last_name'  => $this->approvedBy->last_name,
                'name'       => $this->approvedBy->name,
            ]),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
