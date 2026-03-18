<?php

declare(strict_types=1);

namespace App\Policies\Purchasing;

use App\Models\Auth\User;
use App\Models\Purchasing\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('suppliers.view_any');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('suppliers.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('suppliers.update');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('suppliers.delete');
    }

    public function restore(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('suppliers.restore');
    }

    public function forceDelete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermission('suppliers.force_delete');
    }
}
