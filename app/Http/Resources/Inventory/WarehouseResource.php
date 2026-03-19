<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'code'        => $this->code,
            'name'        => $this->name,
            'address'     => $this->address,
            'city'        => $this->city,
            'state'       => $this->state,
            'country'     => $this->country,
            'postal_code' => $this->postal_code,
            'manager_id'  => $this->manager_id,
            'manager'     => $this->whenLoaded('manager'),
            'is_default'  => $this->is_default,
            'is_active'   => $this->is_active,
            'notes'       => $this->notes,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
