<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChartOfAccountRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $account = $this->route('account');

        return [
            'parent_id'   => ['nullable', 'integer', $this->inCompany('chart_of_accounts'), ...($account ? [Rule::notIn([$account->id])] : [])],
            'code'        => [$required, 'string', 'max:50', Rule::unique('chart_of_accounts', 'code')
                ->where('company_id', $this->user()->current_company_id)->ignore($account)],
            'name'        => [$required, 'string', 'max:255'],
            'type'        => [$required, 'string', 'in:asset,liability,equity,revenue,expense'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
            'balance'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
