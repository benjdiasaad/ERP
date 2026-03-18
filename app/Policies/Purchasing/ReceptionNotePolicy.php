<?php

declare(strict_types=1);

namespace App\Policies\Purchasing;

use App\Models\Auth\User;
use App\Models\Purchasing\ReceptionNote;

class ReceptionNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reception_notes.view_any');
    }

    public function view(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reception_notes.create');
    }

    public function update(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.update');
    }

    public function delete(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.delete');
    }

    public function restore(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.restore');
    }

    public function forceDelete(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.force_delete');
    }

    public function confirm(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.confirm');
    }

    public function cancel(User $user, ReceptionNote $receptionNote): bool
    {
        return $user->hasPermission('reception_notes.cancel');
    }
}
