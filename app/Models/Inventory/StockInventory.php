<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use App\Traits\GeneratesReference;
use App\Traits\HasAuditTrail;
use Database\Factories\Inventory\StockInventoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockInventory extends Model
{
    use BelongsToCompany, GeneratesReference, HasAuditTrail, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return StockInventoryFactory::new();
    }

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'reference',
        'status',
        'counted_at',
        'validated_at',
        'validated_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'counted_at'   => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockInventoryLine::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }
}
