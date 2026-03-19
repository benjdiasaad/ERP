<?php

declare(strict_types=1);

namespace App\Models\Finance;

use App\Traits\BelongsToCompany;
use Database\Factories\Finance\JournalEntryLineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use BelongsToCompany, HasFactory;

    protected static function newFactory(): Factory
    {
        return JournalEntryLineFactory::new();
    }

    protected $fillable = [
        'company_id',
        'journal_entry_id',
        'chart_of_account_id',
        'description',
        'debit',
        'credit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'debit'      => 'decimal:2',
            'credit'     => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Get the journal entry this line belongs to.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the chart of account for this line.
     */
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
