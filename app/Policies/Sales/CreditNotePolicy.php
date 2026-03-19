<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\CreditNote;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('credit_notes.view_any');
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('credit_notes.create');
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.update');
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.delete');
    }

    public function restore(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.restore');
    }

    public function forceDelete(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.force_delete');
    }

    public function confirm(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.confirm');
    }

    public function apply(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.apply');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('credit_notes.export');
    }

    public function print(User $user, CreditNote $creditNote): bool
    {
        return $user->hasPermission('credit_notes.print');
    }
}
