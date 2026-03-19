<?php

declare(strict_types=1);

namespace App\Http\Resources\Caution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CautionTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'description'          => $this->description,
            'default_percentage'   => $this->default_percentage,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
