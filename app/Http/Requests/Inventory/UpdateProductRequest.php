<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'     => ['sometimes', 'nullable', 'integer', $this->inCompany('product_categories')],
            'code'            => ['sometimes', 'string', 'max:100'],
            'name'            => ['sometimes', 'string', 'max:255'],
            'description'     => ['sometimes', 'nullable', 'string'],
            'type'            => ['sometimes', 'nullable', 'in:product,service,consumable'],
            'unit'            => ['sometimes', 'nullable', 'string', 'max:50'],
            'purchase_price'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sale_price'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tax_rate'        => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'barcode'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'min_stock_level' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_stock_level' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'reorder_point'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active'       => ['sometimes', 'nullable', 'boolean'],
            'is_purchasable'  => ['sometimes', 'nullable', 'boolean'],
            'is_sellable'     => ['sometimes', 'nullable', 'boolean'],
            'is_stockable'    => ['sometimes', 'nullable', 'boolean'],
            'notes'           => ['sometimes', 'nullable', 'string'],
        ];
    }
}
