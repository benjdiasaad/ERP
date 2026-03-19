<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\StockInventory;

class StockInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_inventories.view_any');
    }

    public function view(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_inventories.create');
    }

    public function update(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.update');
    }

    public function delete(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.delete');
    }

    public function restore(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.restore');
    }

    public function forceDelete(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.force_delete');
    }

    public function complete(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.complete');
    }

    public function cancel(User $user, StockInventory $inventory): bool
    {
        return $user->hasPermission('stock_inventories.cancel');
    }
}
