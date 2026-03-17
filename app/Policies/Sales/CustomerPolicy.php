<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers.view_any');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.delete');
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.restore');
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customers.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('customers.export');
    }
}
