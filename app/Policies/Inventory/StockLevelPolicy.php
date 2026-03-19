<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\StockLevel;

class StockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_levels.view_any');
    }

    public function view(User $user, StockLevel $level): bool
    {
        return $user->hasPermission('stock_levels.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_levels.create');
    }

    public function update(User $user, StockLevel $level): bool
    {
        return $user->hasPermission('stock_levels.update');
    }

    public function delete(User $user, StockLevel $level): bool
    {
        return $user->hasPermission('stock_levels.delete');
    }

    public function restore(User $user, StockLevel $level): bool
    {
        return $user->hasPermission('stock_levels.restore');
    }

    public function forceDelete(User $user, StockLevel $level): bool
    {
        return $user->hasPermission('stock_levels.force_delete');
    }
}
