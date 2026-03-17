<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'company_id'     => $this->company_id,
            'personnel_id'   => $this->personnel_id,
            'date'           => $this->date?->toDateString(),
            'check_in'       => $this->check_in,
            'check_out'      => $this->check_out,
            'total_hours'    => $this->total_hours,
            'overtime_hours' => $this->overtime_hours,
            'status'         => $this->status,
            'notes'          => $this->notes,
            'created_by'     => $this->created_by,
            'personnel'      => $this->whenLoaded('personnel', fn () => new PersonnelResource($this->personnel)),
            'createdBy'      => $this->whenLoaded('createdBy', fn () => [
                'id'         => $this->createdBy->id,
                'first_name' => $this->createdBy->first_name,
                'last_name'  => $this->createdBy->last_name,
                'name'       => $this->createdBy->name,
            ]),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
