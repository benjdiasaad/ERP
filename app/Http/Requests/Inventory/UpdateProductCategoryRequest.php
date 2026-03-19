<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'code'        => ['sometimes', 'nullable', 'string', 'max:50'],
            'parent_id'   => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active'   => ['sometimes', 'nullable', 'boolean'],
            'sort_order'  => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
