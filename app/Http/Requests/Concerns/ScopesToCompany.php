<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * "exists" rules limited to the current company, so a request can never
 * reference another tenant's records by id.
 */
trait ScopesToCompany
{
    /** Row must belong to the current company (and not be soft-deleted when applicable). */
    protected function inCompany(string $table, string $column = 'id', bool $softDeletes = true): Exists
    {
        $rule = Rule::exists($table, $column)->where('company_id', $this->user()->current_company_id);

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    /** Row must belong to the current company or be shared (company_id null), e.g. currencies, taxes. */
    protected function inCompanyOrShared(string $table): Exists
    {
        $companyId = $this->user()->current_company_id;

        return Rule::exists($table, 'id')
            ->whereNull('deleted_at')
            ->where(fn (Builder $query) => $query->where('company_id', $companyId)->orWhereNull('company_id'));
    }
}
