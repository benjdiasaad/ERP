<?php

declare(strict_types=1);

namespace App\Models\Inventory;

use App\Models\Personnel\Personnel;
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
        'city',
        'state',
        'country',
        'postal_code',
        'manager_id',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Personnel::class, 'manager_id');
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

    /**
     * The current company's default warehouse (or its first active one).
     */
    public static function defaultId(): ?int
    {
        return static::where('is_default', true)->value('id')
            ?? static::where('is_active', true)->orderBy('id')->value('id');
    }
}
