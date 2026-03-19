<?php

declare(strict_types=1);

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'company_id'      => $this->company_id,
            'category_id'     => $this->category_id,
            'code'            => $this->code,
            'name'            => $this->name,
            'description'     => $this->description,
            'type'            => $this->type,
            'unit'            => $this->unit,
            'category'        => $this->whenLoaded('category'),
            'purchase_price'  => $this->purchase_price,
            'sale_price'      => $this->sale_price,
            'tax_rate'        => $this->tax_rate,
            'barcode'         => $this->barcode,
            'image_path'      => $this->image_path,
            'min_stock_level' => $this->min_stock_level,
            'max_stock_level' => $this->max_stock_level,
            'reorder_point'   => $this->reorder_point,
            'is_active'       => $this->is_active,
            'is_purchasable'  => $this->is_purchasable,
            'is_sellable'     => $this->is_sellable,
            'is_stockable'    => $this->is_stockable,
            'notes'           => $this->notes,
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),
        ];
    }
}
