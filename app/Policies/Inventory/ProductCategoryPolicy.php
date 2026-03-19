<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Auth\User;
use App\Models\Inventory\ProductCategory;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('product_categories.view_any');
    }

    public function view(User $user, ProductCategory $category): bool
    {
        return $user->hasPermission('product_categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('product_categories.create');
    }

    public function update(User $user, ProductCategory $category): bool
    {
        return $user->hasPermission('product_categories.update');
    }

    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->hasPermission('product_categories.delete');
    }

    public function restore(User $user, ProductCategory $category): bool
    {
        return $user->hasPermission('product_categories.restore');
    }

    public function forceDelete(User $user, ProductCategory $category): bool
    {
        return $user->hasPermission('product_categories.force_delete');
    }
}
