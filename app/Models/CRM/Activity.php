<?php

namespace App\Models\CRM;

use App\Models\Auth\User;
use App\Models\Sales\Customer;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPES = ['call', 'email', 'meeting', 'task', 'note', 'follow_up'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    public const STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    /** Short aliases accepted by the API for the polymorphic `related` relation. */
    public const RELATED_TYPES = [
        'contact'     => Contact::class,
        'lead'        => Lead::class,
        'opportunity' => Opportunity::class,
        'customer'    => Customer::class,
    ];

    protected $fillable = [
        'company_id',
        'type',
        'subject',
        'description',
        'related_type',
        'related_id',
        'assigned_to',
        'due_date',
        'completed_at',
        'priority',
        'status',
        'outcome',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Human-readable name of the related record (requires the `related` relation).
     */
    public function relatedLabel(): ?string
    {
        return match (true) {
            $this->related instanceof Contact     => $this->related->full_name,
            $this->related instanceof Lead        => "{$this->related->reference} · {$this->related->title}",
            $this->related instanceof Opportunity => "{$this->related->reference} · {$this->related->title}",
            $this->related instanceof Customer    => $this->related->name,
            default                               => null,
        };
    }

    public function getRelatedAliasAttribute(): ?string
    {
        $alias = array_search($this->related_type, self::RELATED_TYPES, true);

        return $alias === false ? null : $alias;
    }
}
