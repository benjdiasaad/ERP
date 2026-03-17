<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Personnel\PersonnelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Personnel extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PersonnelFactory::new();
    }

    protected $fillable = [
        'company_id',
        'user_id',
        'department_id',
        'position_id',
        'matricule',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'gender',
        'birth_date',
        'birth_place',
        'nationality',
        'national_id',
        'social_security_number',
        'address',
        'city',
        'country',
        'photo_path',
        'employment_type',
        'hire_date',
        'termination_date',
        'status',
        'bank_name',
        'bank_account',
        'bank_iban',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'termination_date'  => 'date',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
