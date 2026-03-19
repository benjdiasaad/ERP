<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Tax;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('taxes.view_any');
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->hasPermission('taxes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('taxes.create');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->hasPermission('taxes.update');
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->hasPermission('taxes.delete');
    }

    public function restore(User $user, Tax $tax): bool
    {
        return $user->hasPermission('taxes.restore');
    }

    public function forceDelete(User $user, Tax $tax): bool
    {
        return $user->hasPermission('taxes.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('taxes.export');
    }
}
