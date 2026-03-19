<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'    => ['required', 'integer', 'exists:warehouses,id'],
            'inventory_date'  => ['required', 'date'],
            'status'          => ['nullable', 'in:draft,in_progress,completed,cancelled'],
            'notes'           => ['nullable', 'string'],
            'lines'           => ['required', 'array', 'min:1'],
            'lines.*.product_id'      => ['required', 'integer', 'exists:products,id'],
            'lines.*.theoretical_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.counted_qty'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'           => ['nullable', 'string'],
        ];
    }
}
