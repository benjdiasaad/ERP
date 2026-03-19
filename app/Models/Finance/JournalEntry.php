<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Finance\JournalEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return JournalEntryFactory::new();
    }

    protected $fillable = [
        'company_id',
        'reference',
        'date',
        'description',
        'status',
        'total_debit',
        'total_credit',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'date'          => 'date',
            'total_debit'   => 'decimal:2',
            'total_credit'  => 'decimal:2',
            'posted_at'     => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the journal entry lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Get the user who posted the journal entry.
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Filter draft journal entries only.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /**
     * Filter posted journal entries only.
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }
}
