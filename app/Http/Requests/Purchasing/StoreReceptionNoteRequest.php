<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceptionNoteRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'              => ['required', 'integer', $this->inCompany('purchase_orders')],
            'supplier_id'                    => ['nullable', 'integer', $this->inCompany('suppliers')],
            'reception_date'                 => ['required', 'date'],
            'notes'                          => ['nullable', 'string'],
            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', $this->inCompany('purchase_order_lines', 'id', false)],
            'lines.*.product_id'             => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'            => ['required', 'string'],
            'lines.*.ordered_quantity'       => ['required', 'numeric', 'min:0'],
            'lines.*.received_quantity'      => ['required', 'numeric', 'min:0'],
            'lines.*.rejected_quantity'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit'                   => ['nullable', 'string'],
            'lines.*.notes'                  => ['nullable', 'string'],
            'lines.*.sort_order'             => ['nullable', 'integer'],
        ];
    }
}
