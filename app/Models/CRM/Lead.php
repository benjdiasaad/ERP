<?php

namespace App\Models\CRM;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = ['new', 'contacted', 'qualified', 'unqualified', 'converted', 'lost'];

    /** Statuses from which a lead can still be worked on (edited, qualified, assigned). */
    public const OPEN_STATUSES = ['new', 'contacted', 'qualified', 'unqualified'];

    protected $fillable = [
        'company_id',
        'reference',
        'contact_id',
        'title',
        'description',
        'source',
        'estimated_value',
        'probability',
        'assigned_to',
        'expected_close_date',
        'status',
        'lost_reason',
        'converted_to_opportunity_id',
        'qualified_at',
        'converted_at',
        'tags',
        'created_by',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'qualified_at' => 'datetime',
        'converted_at' => 'datetime',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class, 'converted_to_opportunity_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'related');
    }
}
