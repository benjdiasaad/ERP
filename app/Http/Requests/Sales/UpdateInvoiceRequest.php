<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id'                  => ['sometimes', 'integer', $this->inCompany('customers')],
            'invoice_date'                 => ['sometimes', 'date'],
            'due_date'                     => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'payment_term_id'              => ['nullable', 'integer', $this->inCompanyOrShared('payment_terms')],
            'currency_id'                  => ['nullable', 'integer', $this->inCompanyOrShared('currencies')],
            'notes'                        => ['nullable', 'string', 'max:2000'],
            'terms'                        => ['nullable', 'string', 'max:5000'],
            'lines'                        => ['sometimes', 'array', 'min:1'],
            'lines.*.id'                   => ['nullable', 'integer'],
            'lines.*.product_id'           => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'          => ['required_with:lines', 'string', 'max:500'],
            'lines.*.quantity'             => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit_price_ht'        => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount_type'        => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'       => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'               => ['nullable', 'integer', $this->inCompanyOrShared('taxes')],
            'lines.*.tax_rate'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sort_order'           => ['nullable', 'integer', 'min:0'],
        ];
    }
}
