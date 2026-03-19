<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Database\Factories\Finance\BankAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return BankAccountFactory::new();
    }

    protected $fillable = [
        'company_id',
        'name',
        'bank',
        'account_number',
        'iban',
        'swift',
        'currency_id',
        'balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance'   => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the currency for this bank account.
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter active bank accounts only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
