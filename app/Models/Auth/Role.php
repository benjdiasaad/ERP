<?php

declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'is_system'  => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Permissions assigned to this role (M2M via permission_role pivot).
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    /**
     * Users assigned this role (M2M via role_user pivot with company_id).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot(['company_id'])
            ->withTimestamps();
    }

    /**
     * The company this role belongs to (null = global/system role).
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Scope to roles available for a given company (company-specific + global roles).
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId): void {
            $q->where('company_id', $companyId)
              ->orWhereNull('company_id');
        });
    }
}
