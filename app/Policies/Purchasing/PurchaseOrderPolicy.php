<?php

declare(strict_types=1);

namespace App\Policies\Purchasing;

use App\Models\Auth\User;
use App\Models\Purchasing\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_orders.view_any');
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_orders.create');
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.update');
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.delete');
    }

    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.restore');
    }

    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.force_delete');
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.send');
    }

    public function confirm(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.confirm');
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.cancel');
    }

    public function generateReception(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.generate_reception');
    }

    public function generateInvoice(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.generate_invoice');
    }
}
