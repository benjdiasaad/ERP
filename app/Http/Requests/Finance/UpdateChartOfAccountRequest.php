<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChartOfAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $accountId = $this->route('account')->id;

        return [
            'parent_id'   => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'code'        => ['required', 'string', 'max:50', "unique:chart_of_accounts,code,{$accountId}"],
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'string', 'in:asset,liability,equity,revenue,expense'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['boolean'],
            'balance'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
