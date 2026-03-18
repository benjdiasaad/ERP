<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.view_any');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.delete');
    }

    public function restore(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.restore');
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.force_delete');
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.send');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.cancel');
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.record_payment');
    }

    public function createCreditNote(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.create_credit_note');
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission('invoices.print');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('invoices.export');
    }
}
