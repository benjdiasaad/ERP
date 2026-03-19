<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'      => ['sometimes', 'integer', 'exists:products,id'],
            'warehouse_id'    => ['sometimes', 'integer', 'exists:warehouses,id'],
            'type'            => ['sometimes', 'in:in,out,transfer,adjustment,return,initial'],
            'quantity'        => ['sometimes', 'numeric', 'min:0.0001'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'source_type'     => ['nullable', 'string', 'max:255'],
            'source_id'       => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string'],
            'movement_date'   => ['nullable', 'date'],
        ];
    }
}
