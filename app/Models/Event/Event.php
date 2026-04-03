<?php

namespace App\Models\Event;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'event_category_id',
        'reference',
        'title',
        'type',
        'location',
        'description',
        'start_date',
        'end_date',
        'budget',
        'is_mandatory',
        'recurring_pattern',
        'status',
        'created_by',
        'updated_by',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'completed_by',
        'completed_at',
        'postponed_by',
        'postponed_at',
        'postponement_reason',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'budget' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'recurring_pattern' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EventParticipant::class);
    }
}
