<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class CrmFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Exists rule restricted to the current company (and non-deleted rows when soft-deletable).
     */
    protected function inCompany(string $table, string $column = 'id', bool $softDeletes = true): Exists
    {
        $rule = Rule::exists($table, $column)->where('company_id', $this->user()->current_company_id);

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }
}
