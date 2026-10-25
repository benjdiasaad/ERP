<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesOrderRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'                  => ['required', 'integer', $this->inCompany('customers')],
            'quote_id'                     => ['nullable', 'integer', $this->inCompany('quotes')],
            'order_date'                   => ['required', 'date'],
            'expected_delivery_date'       => ['nullable', 'date', 'after_or_equal:order_date'],
            'delivery_address'             => ['nullable', 'string', 'max:500'],
            'currency_id'                  => ['nullable', 'integer', $this->inCompanyOrShared('currencies')],
            'payment_term_id'              => ['nullable', 'integer', $this->inCompanyOrShared('payment_terms')],
            'notes'                        => ['nullable', 'string', 'max:2000'],
            'terms_conditions'             => ['nullable', 'string', 'max:5000'],
            'lines'                        => ['required', 'array', 'min:1'],
            'lines.*.product_id'           => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'          => ['required', 'string', 'max:500'],
            'lines.*.quantity'             => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht'        => ['required', 'numeric', 'min:0'],
            'lines.*.discount_type'        => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'               => ['nullable', 'integer', $this->inCompanyOrShared('taxes')],
            'lines.*.tax_rate'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sort_order'           => ['nullable', 'integer', 'min:0'],
        ];
    }
}
