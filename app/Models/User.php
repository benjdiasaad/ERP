<?php

namespace App\Models;

use App\Models\Company\Company;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'current_company_id' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['is_default', 'joined_at'])
            ->withTimestamps();
    }

    public function currentCompany()
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    // ─── Permissions ──────────────────────────────────────────────────────────

    /**
     * Check if the user has a given permission via their roles in the current company.
     * Full implementation is in Task 4 (Roles & Permissions module).
     * Returns true by default until RBAC is implemented.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // TODO: implement full RBAC check in Task 4
        // For now, all authenticated users have all permissions
        return true;
    }
}
