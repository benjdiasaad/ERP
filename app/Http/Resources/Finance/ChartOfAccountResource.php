<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChartOfAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'parent_id'   => $this->parent_id,
            'code'        => $this->code,
            'name'        => $this->name,
            'type'        => $this->type,
            'description' => $this->description,
            'is_active'   => $this->is_active,
            'balance'     => (float) $this->balance,
            'parent'      => new self($this->whenLoaded('parent')),
            'children'    => self::collection($this->whenLoaded('children')),
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
