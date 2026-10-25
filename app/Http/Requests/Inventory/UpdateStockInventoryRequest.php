<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStockInventoryRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'count_date'              => ['sometimes', 'nullable', 'date'],
            'notes'                   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines'                   => ['sometimes', 'array'],
            'lines.*.id'              => ['nullable', 'integer'],
            'lines.*.product_id'      => ['required_without:lines.*.id', 'integer', $this->inCompany('products')],
            'lines.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
