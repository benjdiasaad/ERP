<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'             => ['sometimes', 'integer', 'exists:suppliers,id'],
            'purchase_request_id'     => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'order_date'              => ['sometimes', 'date'],
            'expected_delivery_date'  => ['nullable', 'date'],
            'delivery_address'        => ['nullable', 'string'],
            'payment_term_id'         => ['nullable', 'integer', 'exists:payment_terms,id'],
            'currency_id'             => ['nullable', 'integer', 'exists:currencies,id'],
            'notes'                   => ['nullable', 'string'],
            'terms_conditions'        => ['nullable', 'string'],
            'lines'                   => ['sometimes', 'array', 'min:1'],
            'lines.*.id'              => ['nullable', 'integer'],
            'lines.*.product_id'      => ['nullable', 'integer'],
            'lines.*.description'     => ['required_with:lines', 'string'],
            'lines.*.quantity'        => ['required_with:lines', 'numeric', 'min:0.0001'],
            'lines.*.unit'            => ['nullable', 'string'],
            'lines.*.unit_price_ht'   => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount_type'   => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'          => ['nullable', 'integer'],
            'lines.*.tax_rate'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.sort_order'      => ['nullable', 'integer'],
        ];
    }
}
