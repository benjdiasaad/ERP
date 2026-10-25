<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockMovementRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'      => ['required', 'integer', $this->inCompany('products')],
            'warehouse_id'    => ['required', 'integer', $this->inCompany('warehouses')],
            'type'            => ['required', 'in:in,out,adjustment,return'],
            'quantity'        => ['required', 'numeric', 'min:0.0001'],
            'reference'       => ['nullable', 'string', 'max:255'],
            'source_type'     => ['nullable', 'string', 'max:255'],
            'source_id'       => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string'],
        ];
    }
}
