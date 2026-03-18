<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                      => ['sometimes', 'required', 'string', 'max:255'],
            'supplier_id'                => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'description'                => ['sometimes', 'nullable', 'string'],
            'priority'                   => ['sometimes', 'nullable', 'in:low,medium,high,urgent'],
            'required_date'              => ['sometimes', 'nullable', 'date'],
            'notes'                      => ['sometimes', 'nullable', 'string'],
            'lines'                      => ['sometimes', 'array', 'min:1'],
            'lines.*.description'        => ['sometimes', 'required', 'string'],
            'lines.*.quantity'           => ['sometimes', 'required', 'numeric', 'min:0.0001'],
            'lines.*.unit'               => ['sometimes', 'nullable', 'string'],
            'lines.*.estimated_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.notes'              => ['sometimes', 'nullable', 'string'],
        ];
    }
}
