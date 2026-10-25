<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequestRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                      => ['required', 'string', 'max:255'],
            'supplier_id'                => ['nullable', 'integer', $this->inCompany('suppliers')],
            'description'                => ['nullable', 'string'],
            'priority'                   => ['nullable', 'in:low,medium,high,urgent'],
            'required_date'              => ['nullable', 'date'],
            'notes'                      => ['nullable', 'string'],
            'lines'                      => ['required', 'array', 'min:1'],
            'lines.*.description'        => ['required', 'string'],
            'lines.*.quantity'           => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit'               => ['nullable', 'string'],
            'lines.*.estimated_unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes'              => ['nullable', 'string'],
        ];
    }
}
