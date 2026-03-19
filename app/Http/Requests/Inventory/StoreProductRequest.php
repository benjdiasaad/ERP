<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'     => ['nullable', 'integer', 'exists:product_categories,id'],
            'code'            => ['nullable', 'string', 'max:100'],
            'name'            => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'type'            => ['nullable', 'in:product,service,consumable'],
            'unit'            => ['nullable', 'string', 'max:50'],
            'purchase_price'  => ['nullable', 'numeric', 'min:0'],
            'sale_price'      => ['nullable', 'numeric', 'min:0'],
            'tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'barcode'         => ['nullable', 'string', 'max:100'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'max_stock_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_point'   => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
            'is_purchasable'  => ['nullable', 'boolean'],
            'is_sellable'     => ['nullable', 'boolean'],
            'is_stockable'    => ['nullable', 'boolean'],
            'notes'           => ['nullable', 'string'],
        ];
    }
}
