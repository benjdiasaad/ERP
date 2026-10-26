<?php

declare(strict_types=1);

namespace App\Http\Requests\Caution;

use App\Http\Requests\Concerns\ScopesToCompany;
use App\Models\Caution\Caution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCautionRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The partner may be given as an alias (customer, supplier, other) or as a model class.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('partner_type')) {
            $alias = $this->input('partner_type');
            $type = is_string($alias) && array_key_exists($alias, Caution::PARTNER_TYPES) ? Caution::PARTNER_TYPES[$alias] : $alias;
            $this->merge([
                'partner_type' => $type,
                'partner_id'   => $type ? $this->input('partner_id') : null,
            ]);
        }
    }

    public function rules(): array
    {
        $partnerTable = match ($this->input('partner_type')) {
            Caution::PARTNER_TYPES['customer'] => 'customers',
            Caution::PARTNER_TYPES['supplier'] => 'suppliers',
            default                            => null,
        };

        return [
            'caution_type_id'   => ['required', 'integer', $this->inCompany('caution_types', 'id', false)],
            'direction'         => ['required', 'in:given,received'],
            'partner_type'      => ['present', 'nullable', Rule::in(array_filter(Caution::PARTNER_TYPES))],
            'partner_id'        => array_filter(['nullable', 'required_with:partner_type', 'integer', $partnerTable ? $this->inCompany($partnerTable) : null]),
            'related_type'      => ['nullable', 'string', 'max:255'],
            'related_id'        => ['nullable', 'integer'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'issue_date'        => ['required', 'date'],
            'expiry_date'       => ['required', 'date', 'after:issue_date'],
            'bank_name'         => ['nullable', 'string', 'max:255'],
            'bank_account'      => ['nullable', 'string', 'max:255'],
            'bank_reference'    => ['nullable', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
