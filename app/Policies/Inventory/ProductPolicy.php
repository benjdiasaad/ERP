<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view_any');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('products.delete');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasPermission('products.restore');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasPermission('products.force_delete');
    }
}
