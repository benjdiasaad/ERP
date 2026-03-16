<?php

declare(strict_types=1);

namespace App\Models\Auth;

use App\Models\Company\Company;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'matricule',
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar_path',
        'current_company_id',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'password'            => 'hashed',
            'current_company_id'  => 'integer',
            'is_active'           => 'boolean',
            'last_login_at'       => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user): void {
            if (empty($user->matricule)) {
                $year  = now()->format('Y');
                $count = static::withTrashed()->whereYear('created_at', $year)->count() + 1;
                $user->matricule = sprintf('EMP-%s-%05d', $year, $count);
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * Roles are scoped per company via the company_id pivot column on role_user.
     * Full Role model is created in Task 4.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot(['company_id'])
            ->withTimestamps();
    }

    /**
     * Optional 1:1 link to a Personnel record (not all users are personnel).
     * Personnel model is created in Task 5.
     */
    public function personnel(): HasOne
    {
        return $this->hasOne(\App\Models\Personnel\Personnel::class);
    }

    // ─── Permission Helpers ───────────────────────────────────────────────────

    /**
     * Check if the user has a given permission via any of their roles in the current company.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->roles()
            ->wherePivot('company_id', $this->current_company_id)
            ->whereHas('permissions', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }

    /**
     * Check if the user has a given role (by slug) in the current company.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()
            ->wherePivot('company_id', $this->current_company_id)
            ->where('slug', $roleSlug)
            ->exists();
    }

    /**
     * Get all roles for a specific company (defaults to current company).
     */
    public function getRolesForCompany(?int $companyId = null): Collection
    {
        $companyId ??= $this->current_company_id;

        return $this->roles()
            ->wherePivot('company_id', $companyId)
            ->get();
    }
}
