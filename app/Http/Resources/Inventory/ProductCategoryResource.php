<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'parent_id'      => $this->parent_id,
            'name'           => $this->name,
            'code'           => $this->code,
            'description'    => $this->description,
            'image_path'     => $this->image_path,
            'is_active'      => $this->is_active,
            'sort_order'     => $this->sort_order,
            'parent'         => $this->whenLoaded('parent'),
            'children'       => ProductCategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->whenCounted('products'),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
