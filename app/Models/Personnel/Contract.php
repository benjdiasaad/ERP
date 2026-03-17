<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Personnel\ContractFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return ContractFactory::new();
    }

    protected $fillable = [
        'company_id',
        'personnel_id',
        'reference',
        'type',
        'status',
        'start_date',
        'end_date',
        'trial_period_end_date',
        'salary',
        'salary_currency',
        'working_hours_per_week',
        'benefits',
        'document_path',
        'notes',
        'signed_at',
        'terminated_at',
        'termination_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date'            => 'date',
            'end_date'              => 'date',
            'trial_period_end_date' => 'date',
            'salary'                => 'decimal:2',
            'working_hours_per_week' => 'decimal:2',
            'benefits'              => 'array',
            'signed_at'             => 'datetime',
            'terminated_at'         => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
