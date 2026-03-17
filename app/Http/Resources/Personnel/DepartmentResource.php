<?php

declare(strict_types=1);

namespace App\Http\Resources\Personnel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'company_id'  => $this->company_id,
            'parent_id'   => $this->parent_id,
            'name'        => $this->name,
            'code'        => $this->code,
            'description' => $this->description,
            'manager_id'  => $this->manager_id,
            'is_active'   => $this->is_active,
            'parent'      => $this->whenLoaded('parent', fn () => new DepartmentResource($this->parent)),
            'manager'     => $this->whenLoaded('manager', fn () => [
                'id'         => $this->manager->id,
                'first_name' => $this->manager->first_name,
                'last_name'  => $this->manager->last_name,
                'name'       => $this->manager->name,
            ]),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
