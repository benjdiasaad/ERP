<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('warehouses.view_any');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.delete');
    }

    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.restore');
    }

    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.force_delete');
    }
}
