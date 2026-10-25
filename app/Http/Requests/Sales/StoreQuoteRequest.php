<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'              => ['required', 'integer', $this->inCompany('customers')],
            'date'                     => ['required', 'date'],
            'validity_date'            => ['required', 'date', 'after_or_equal:date'],
            'currency_id'              => ['nullable', 'integer', $this->inCompanyOrShared('currencies')],
            'payment_term_id'          => ['nullable', 'integer', $this->inCompanyOrShared('payment_terms')],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'discount_type'            => ['nullable', 'in:percentage,fixed'],
            'discount_value'           => ['nullable', 'numeric', 'min:0'],
            'lines'                    => ['required', 'array', 'min:1'],
            'lines.*.product_id'       => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'      => ['required', 'string', 'max:500'],
            'lines.*.quantity'         => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht'    => ['required', 'numeric', 'min:0'],
            'lines.*.discount_type'    => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'           => ['nullable', 'integer', $this->inCompanyOrShared('taxes')],
            'lines.*.tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
