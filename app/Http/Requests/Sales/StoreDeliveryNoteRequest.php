<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'                  => ['required', 'integer', 'exists:customers,id'],
            'sales_order_id'               => ['nullable', 'integer', 'exists:sales_orders,id'],
            'date'                         => ['required', 'date'],
            'expected_delivery_date'       => ['nullable', 'date', 'after_or_equal:date'],
            'delivery_address'             => ['nullable', 'string', 'max:500'],
            'carrier'                      => ['nullable', 'string', 'max:255'],
            'tracking_number'              => ['nullable', 'string', 'max:255'],
            'notes'                        => ['nullable', 'string', 'max:2000'],
            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.sales_order_line_id'  => ['nullable', 'integer', 'exists:sales_order_lines,id'],
            'lines.*.product_id'           => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description'          => ['required', 'string', 'max:500'],
            'lines.*.ordered_quantity'     => ['required', 'numeric', 'min:0.01'],
            'lines.*.shipped_quantity'     => ['required', 'numeric', 'min:0'],
            'lines.*.unit'                 => ['nullable', 'string', 'max:50'],
            'lines.*.notes'                => ['nullable', 'string', 'max:500'],
            'lines.*.sort_order'           => ['nullable', 'integer', 'min:0'],
        ];
    }
}
