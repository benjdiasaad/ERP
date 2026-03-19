<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\StockMovement;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('stock_movements.view_any');
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $user->hasPermission('stock_movements.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('stock_movements.create');
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return $user->hasPermission('stock_movements.update');
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return $user->hasPermission('stock_movements.delete');
    }

    public function restore(User $user, StockMovement $movement): bool
    {
        return $user->hasPermission('stock_movements.restore');
    }

    public function forceDelete(User $user, StockMovement $movement): bool
    {
        return $user->hasPermission('stock_movements.force_delete');
    }
}
