<?php

declare(strict_types=1);

namespace App\Policies\Purchasing;

use App\Models\Auth\User;
use App\Models\Purchasing\PurchaseInvoice;

class PurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_invoices.view_any');
    }

    public function view(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_invoices.create');
    }

    public function update(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.update');
    }

    public function delete(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.delete');
    }

    public function restore(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.restore');
    }

    public function forceDelete(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.force_delete');
    }

    public function send(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.send');
    }

    public function cancel(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.cancel');
    }

    public function recordPayment(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.record_payment');
    }
}
