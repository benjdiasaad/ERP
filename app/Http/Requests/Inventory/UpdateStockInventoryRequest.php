<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'    => ['sometimes', 'integer', 'exists:warehouses,id'],
            'inventory_date'  => ['sometimes', 'date'],
            'status'          => ['nullable', 'in:draft,in_progress,completed,cancelled'],
            'notes'           => ['nullable', 'string'],
            'lines'           => ['sometimes', 'array', 'min:1'],
            'lines.*.id'              => ['nullable', 'integer'],
            'lines.*.product_id'      => ['required_with:lines', 'integer', 'exists:products,id'],
            'lines.*.theoretical_qty' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.counted_qty'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'           => ['nullable', 'string'],
        ];
    }
}
