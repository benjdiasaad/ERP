<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockInventoryRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'            => ['required', 'integer', $this->inCompany('warehouses')],
            'reference'               => ['nullable', 'string', 'max:100'],
            'count_date'              => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
            'lines'                   => ['nullable', 'array'],
            'lines.*.product_id'      => ['required', 'integer', 'distinct', $this->inCompany('products')],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
