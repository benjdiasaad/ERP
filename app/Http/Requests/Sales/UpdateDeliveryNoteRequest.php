<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expected_delivery_date'   => ['nullable', 'date'],
            'delivery_address'         => ['nullable', 'string', 'max:1000'],
            'carrier'                  => ['nullable', 'string', 'max:255'],
            'tracking_number'          => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'lines'                    => ['nullable', 'array'],
            'lines.*.id'               => ['nullable', 'integer', 'exists:delivery_note_lines,id'],
            'lines.*.sales_order_line_id' => ['nullable', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.product_id'       => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description'      => ['required_with:lines', 'string', 'max:500'],
            'lines.*.ordered_quantity' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.shipped_quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit'             => ['nullable', 'string', 'max:50'],
            'lines.*.sort_order'       => ['nullable', 'integer', 'min:0'],
            'lines.*.notes'            => ['nullable', 'string', 'max:500'],
        ];
    }
}
