<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'              => ['sometimes', 'integer', 'exists:customers,id'],
            'date'                     => ['sometimes', 'date'],
            'validity_date'            => ['sometimes', 'date', 'after_or_equal:date'],
            'currency_id'              => ['sometimes', 'nullable', 'integer', 'exists:currencies,id'],
            'payment_term_id'          => ['sometimes', 'nullable', 'integer', 'exists:payment_terms,id'],
            'notes'                    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'discount_type'            => ['sometimes', 'nullable', 'in:percentage,fixed'],
            'discount_value'           => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines'                    => ['sometimes', 'array', 'min:1'],
            'lines.*.product_id'       => ['sometimes', 'nullable', 'integer', 'exists:products,id'],
            'lines.*.description'      => ['sometimes', 'required', 'string', 'max:500'],
            'lines.*.quantity'         => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'lines.*.discount_type'    => ['sometimes', 'nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'           => ['sometimes', 'nullable', 'integer', 'exists:taxes,id'],
            'lines.*.tax_rate'         => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
