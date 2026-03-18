<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReceptionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reception_date'                 => ['sometimes', 'date'],
            'notes'                          => ['nullable', 'string'],
            'lines'                          => ['sometimes', 'array', 'min:1'],
            'lines.*.id'                     => ['nullable', 'integer'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.product_id'             => ['nullable', 'integer'],
            'lines.*.description'            => ['required_with:lines', 'string'],
            'lines.*.ordered_quantity'       => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.received_quantity'      => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.rejected_quantity'      => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit'                   => ['nullable', 'string'],
            'lines.*.notes'                  => ['nullable', 'string'],
            'lines.*.sort_order'             => ['nullable', 'integer'],
        ];
    }
}
