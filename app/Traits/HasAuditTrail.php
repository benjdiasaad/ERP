<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

trait HasAuditTrail
{
    protected static function bootHasAuditTrail(): void
    {
        static::created(function (self $model): void {
            static::writeAuditLog($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (self $model): void {
            $dirty = array_keys($model->getDirty());
            $oldValues = array_intersect_key($model->getOriginal(), array_flip($dirty));
            $newValues = array_intersect_key($model->getAttributes(), array_flip($dirty));

            static::writeAuditLog($model, 'updated', $oldValues, $newValues);
        });

        static::deleted(function (self $model): void {
            static::writeAuditLog($model, 'deleted', $model->getAttributes(), null);
        });
    }

    private static function writeAuditLog(self $model, string $event, ?array $oldValues, ?array $newValues): void
    {
        try {
            // Use a savepoint so a failed insert does not abort the outer
            // PostgreSQL transaction (e.g. when the audit_logs table does not
            // exist yet during early migrations or tests).
            DB::statement('SAVEPOINT audit_log_savepoint');

            DB::table('audit_logs')->insert([
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'event' => $event,
                'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
                'new_values' => $newValues !== null ? json_encode($newValues) : null,
                'user_id' => auth()->id(),
                'company_id' => auth()->user()?->current_company_id,
                'ip_address' => Request::ip(),
                'url' => Request::fullUrl(),
                'user_agent' => Request::userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // audit_logs table may not exist yet — roll back to savepoint so
            // the outer transaction remains usable (critical for PostgreSQL).
            try {
                DB::statement('ROLLBACK TO SAVEPOINT audit_log_savepoint');
            } catch (\Throwable) {
                // Savepoint may not exist if we are outside a transaction — ignore.
            }
        }
    }
}
