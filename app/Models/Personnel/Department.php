<?php

declare(strict_types=1);

namespace App\Models\Personnel;

use App\Models\Auth\User;
use App\Traits\BelongsToCompany;
use Database\Factories\Personnel\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected static function newFactory(): Factory
    {
        return DepartmentFactory::new();
    }

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'code',
        'description',
        'manager_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function personnels(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }
}
