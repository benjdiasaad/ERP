<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseInvoiceRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'             => ['required', 'integer', $this->inCompany('suppliers')],
            'purchase_order_id'       => ['nullable', 'integer', $this->inCompany('purchase_orders')],
            'invoice_date'            => ['required', 'date'],
            'due_date'                => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'payment_term_id'         => ['nullable', 'integer', $this->inCompanyOrShared('payment_terms')],
            'currency_id'             => ['nullable', 'integer', $this->inCompanyOrShared('currencies')],
            'notes'                   => ['nullable', 'string'],
            'terms_conditions'        => ['nullable', 'string'],
            'lines'                   => ['required', 'array', 'min:1'],
            'lines.*.product_id'      => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'     => ['required', 'string'],
            'lines.*.quantity'        => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit'            => ['nullable', 'string'],
            'lines.*.unit_price_ht'   => ['required', 'numeric', 'min:0'],
            'lines.*.discount_type'   => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'          => ['nullable', 'integer', $this->inCompanyOrShared('taxes')],
            'lines.*.tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sort_order'      => ['nullable', 'integer'],
        ];
    }
}
