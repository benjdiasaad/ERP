<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'company_id'    => $this->company_id,
            'department_id' => $this->department_id,
            'name'          => $this->name,
            'code'          => $this->code,
            'description'   => $this->description,
            'salary_min'    => $this->salary_min,
            'salary_max'    => $this->salary_max,
            'is_active'     => $this->is_active,
            'department'    => $this->whenLoaded('department', fn () => new DepartmentResource($this->department)),
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
