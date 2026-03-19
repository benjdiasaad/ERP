<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\ChartOfAccount;

class ChartOfAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('chart_of_accounts.view_any');
    }

    public function view(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermission('chart_of_accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('chart_of_accounts.create');
    }

    public function update(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermission('chart_of_accounts.update');
    }

    public function delete(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermission('chart_of_accounts.delete');
    }

    public function restore(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermission('chart_of_accounts.restore');
    }

    public function forceDelete(User $user, ChartOfAccount $account): bool
    {
        return $user->hasPermission('chart_of_accounts.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('chart_of_accounts.export');
    }
}
