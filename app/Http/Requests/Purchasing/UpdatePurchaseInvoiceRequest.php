<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseInvoiceRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'             => ['sometimes', 'integer', $this->inCompany('suppliers')],
            'purchase_order_id'       => ['nullable', 'integer', $this->inCompany('purchase_orders')],
            'invoice_date'            => ['sometimes', 'date'],
            'due_date'                => ['nullable', 'date'],
            'payment_term_id'         => ['nullable', 'integer', $this->inCompanyOrShared('payment_terms')],
            'currency_id'             => ['nullable', 'integer', $this->inCompanyOrShared('currencies')],
            'notes'                   => ['nullable', 'string'],
            'terms_conditions'        => ['nullable', 'string'],
            'lines'                   => ['sometimes', 'array', 'min:1'],
            'lines.*.id'              => ['nullable', 'integer'],
            'lines.*.product_id'      => ['nullable', 'integer', $this->inCompany('products')],
            'lines.*.description'     => ['required_with:lines', 'string'],
            'lines.*.quantity'        => ['required_with:lines', 'numeric', 'min:0.0001'],
            'lines.*.unit'            => ['nullable', 'string'],
            'lines.*.unit_price_ht'   => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount_type'   => ['nullable', 'in:percentage,fixed'],
            'lines.*.discount_value'  => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_id'          => ['nullable', 'integer', $this->inCompanyOrShared('taxes')],
            'lines.*.tax_rate'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.sort_order'      => ['nullable', 'integer'],
        ];
    }
}
