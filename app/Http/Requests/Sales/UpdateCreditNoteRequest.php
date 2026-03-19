<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason'                   => ['nullable', 'string', 'max:500'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'lines'                    => ['nullable', 'array'],
            'lines.*.id'               => ['nullable', 'integer', 'exists:credit_note_lines,id'],
            'lines.*.product_id'       => ['nullable', 'integer', 'exists:products,id'],
            'lines.*.description'      => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity'         => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht'    => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount_type'    => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sort_order'       => ['nullable', 'integer', 'min:0'],
        ];
    }
}
