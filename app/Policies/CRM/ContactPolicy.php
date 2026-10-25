<?php

declare(strict_types=1);

namespace App\Policies\CRM;

use App\Models\Auth\User;
use App\Models\CRM\Contact;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.view_any');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.update');
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.delete');
    }

    public function restore(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.restore');
    }

    public function forceDelete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('contacts.export');
    }

    public function merge(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.merge');
    }
}
