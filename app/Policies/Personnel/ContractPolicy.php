<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Contract;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contracts.view_any');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.view')
            && $contract->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contracts.create');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.update')
            && $contract->company_id === $user->current_company_id;
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.delete')
            && $contract->company_id === $user->current_company_id;
    }

    public function restore(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.restore')
            && $contract->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.force_delete')
            && $contract->company_id === $user->current_company_id;
    }

    public function print(User $user, Contract $contract): bool
    {
        return $user->hasPermission('contracts.print')
            && $contract->company_id === $user->current_company_id;
    }
}
