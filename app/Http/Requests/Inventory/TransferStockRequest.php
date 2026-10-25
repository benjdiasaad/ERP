<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class TransferStockRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'        => ['required', 'integer', $this->inCompany('products')],
            'from_warehouse_id' => ['required', 'integer', $this->inCompany('warehouses')],
            'to_warehouse_id'   => ['required', 'integer', 'different:from_warehouse_id', $this->inCompany('warehouses')],
            'quantity'          => ['required', 'numeric', 'min:0.0001'],
            'reference'         => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:2000'],
        ];
    }
}
