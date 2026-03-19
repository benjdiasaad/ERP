<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\BankAccount;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bank_accounts.view_any');
    }

    public function view(User $user, BankAccount $account): bool
    {
        return $user->hasPermission('bank_accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('bank_accounts.create');
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $user->hasPermission('bank_accounts.update');
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $user->hasPermission('bank_accounts.delete');
    }

    public function restore(User $user, BankAccount $account): bool
    {
        return $user->hasPermission('bank_accounts.restore');
    }

    public function forceDelete(User $user, BankAccount $account): bool
    {
        return $user->hasPermission('bank_accounts.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('bank_accounts.export');
    }
}
