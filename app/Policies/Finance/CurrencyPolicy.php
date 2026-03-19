<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Currency;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('currencies.view_any');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermission('currencies.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('currencies.create');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermission('currencies.update');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasPermission('currencies.delete');
    }

    public function restore(User $user, Currency $currency): bool
    {
        return $user->hasPermission('currencies.restore');
    }

    public function forceDelete(User $user, Currency $currency): bool
    {
        return $user->hasPermission('currencies.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('currencies.export');
    }
}
