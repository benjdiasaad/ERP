<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Personnel\AttendanceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return AttendanceFactory::new();
    }

    protected $fillable = [
        'company_id',
        'personnel_id',
        'date',
        'check_in',
        'check_out',
        'total_hours',
        'overtime_hours',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date'           => 'date',
            'total_hours'    => 'decimal:2',
            'overtime_hours' => 'decimal:2',
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
