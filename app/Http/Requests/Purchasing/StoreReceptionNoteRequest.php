<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceptionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id'              => ['required', 'integer', 'exists:purchase_orders,id'],
            'supplier_id'                    => ['nullable', 'integer', 'exists:suppliers,id'],
            'reception_date'                 => ['required', 'date'],
            'notes'                          => ['nullable', 'string'],
            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.product_id'             => ['nullable', 'integer'],
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
