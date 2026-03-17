<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\SalesOrder;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales_orders.view_any');
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_orders.create');
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.update');
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.delete');
    }

    public function restore(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.restore');
    }

    public function forceDelete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.force_delete');
    }

    public function confirm(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.confirm');
    }

    public function cancel(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.cancel');
    }

    public function generateInvoice(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.generate_invoice');
    }

    public function generateDeliveryNote(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.generate_delivery_note');
    }

    public function print(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasPermission('sales_orders.print');
    }
}
