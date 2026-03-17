<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Traits\BelongsToCompany;
use Database\Factories\Personnel\PositionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Position extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return PositionFactory::new();
    }

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'code',
        'description',
        'salary_min',
        'salary_max',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'is_active'  => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }
}
