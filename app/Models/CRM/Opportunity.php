<?php

namespace App\Models\CRM;

use App\Models\Auth\User;
use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** Open pipeline stages, in order. */
    public const OPEN_STAGES = ['prospecting', 'qualification', 'proposal', 'negotiation'];

    public const CLOSED_STAGES = ['closed_won', 'closed_lost'];

    /** Default win probability (%) applied when an opportunity enters a stage. */
    public const STAGE_PROBABILITIES = [
        'prospecting'   => 10,
        'qualification' => 25,
        'proposal'      => 50,
        'negotiation'   => 75,
        'closed_won'    => 100,
        'closed_lost'   => 0,
    ];

    protected $fillable = [
        'company_id',
        'reference',
        'lead_id',
        'contact_id',
        'customer_id',
        'title',
        'description',
        'value',
        'probability',
        'weighted_value',
        'stage',
        'expected_close_date',
        'actual_close_date',
        'assigned_to',
        'lost_reason',
        'won_quote_id',
        'tags',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'probability' => 'integer',
        'weighted_value' => 'decimal:2',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $opportunity): void {
            $opportunity->weighted_value = static::calculateWeightedValue(
                (float) $opportunity->value,
                (int) $opportunity->probability
            );
        });
    }

    public static function calculateWeightedValue(float $value, int $probability): float
    {
        return round($value * max(0, min(100, $probability)) / 100, 2);
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN_STAGES, true);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function wonQuote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'won_quote_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }
}
