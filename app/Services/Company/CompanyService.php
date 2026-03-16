<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Company\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    /**
     * Create a new company and attach the creator as a default member.
     */
    public function create(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::create($data);

            if ($userId = auth()->id()) {
                $company->users()->attach($userId, [
                    'is_default' => true,
                    'joined_at'  => now(),
                ]);

                // Set as current company if user has none
                $user = auth()->user();
                if (!$user->current_company_id) {
                    $user->update(['current_company_id' => $company->id]);
                }
            }

            return $company->fresh(['users']);
        });
    }

    /**
     * Update an existing company.
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company->fresh(['users']);
    }

    /**
     * Soft-delete a company.
     *
     * Allows deletion when the only remaining member is the authenticated user
     * (they are auto-detached). Throws if other users are still attached.
     *
     * @throws ValidationException if the company still has other active users.
     */
    public function delete(Company $company): void
    {
        $authId = auth()->id();
        $otherUserCount = $company->users()
            ->when($authId, fn ($q) => $q->where('users.id', '!=', $authId))
            ->count();

        if ($otherUserCount > 0) {
            throw ValidationException::withMessages([
                'company' => "Cannot delete a company that still has {$otherUserCount} other user(s) attached.",
            ]);
        }

        // Detach the authenticated user if they are the sole member
        if ($authId) {
            $company->users()->detach($authId);
        }

        $company->delete();
    }

    /**
     * Add a user to a company.
     *
     * @throws ValidationException if the user is already a member.
     */
    public function addUser(Company $company, User $user, bool $isDefault = false): void
    {
        if ($company->users()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'User is already a member of this company.',
            ]);
        }

        $company->users()->attach($user->id, [
            'is_default' => $isDefault,
            'joined_at'  => now(),
        ]);

        // If this is the user's first company, set it as current
        if (!$user->current_company_id) {
            $user->update(['current_company_id' => $company->id]);
        }
    }

    /**
     * Remove a user from a company.
     *
     * @throws ValidationException if the user is not a member or is the last member.
     */
    public function removeUser(Company $company, User $user): void
    {
        if (!$company->users()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'User is not a member of this company.',
            ]);
        }

        $company->users()->detach($user->id);

        // If this was the user's current company, clear or switch it
        if ((int) $user->current_company_id === $company->id) {
            $nextCompany = $user->companies()->first();
            $user->update(['current_company_id' => $nextCompany?->id]);
        }
    }

    /**
     * Switch the authenticated user's active company.
     *
     * @throws ValidationException if the user does not belong to the target company.
     */
    public function switchCompany(User $user, int $companyId): Company
    {
        $company = $user->companies()->where('companies.id', $companyId)->first();

        if (!$company) {
            throw ValidationException::withMessages([
                'company' => 'You do not have access to this company.',
            ]);
        }

        $user->update(['current_company_id' => $companyId]);

        return $company;
    }

    /**
     * Get all companies the given user belongs to.
     */
    public function getUserCompanies(User $user): Collection
    {
        return $user->companies()->withPivot(['is_default', 'joined_at'])->get();
    }
}
