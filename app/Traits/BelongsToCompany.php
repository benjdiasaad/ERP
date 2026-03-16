<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function (self $model): void {
            if (!$model->company_id) {
                $model->company_id = auth()->user()?->current_company_id;
            }
        });

        static::addGlobalScope('company', function (Builder $query): void {
            if (auth()->check()) {
                $query->where(
                    $query->getModel()->getTable() . '.company_id',
                    auth()->user()->current_company_id
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
