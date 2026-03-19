<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use App\Traits\HasAuditTrail;
use Database\Factories\Inventory\WarehouseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToCompany, HasAuditTrail, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return WarehouseFactory::new();
    }

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'address',
        'manager_id',
        'default',
    ];

    protected function casts(): array
    {
        return [
            'default' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }
}
