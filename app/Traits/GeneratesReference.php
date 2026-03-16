<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\Settings\SequenceService;

trait GeneratesReference
{
    protected static function bootGeneratesReference(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->reference)) {
                $model->reference = app(SequenceService::class)
                    ->getNextNumber(static::class);
            }
        });
    }
}
