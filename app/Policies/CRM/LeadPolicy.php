<?php

declare(strict_types=1);

namespace App\Policies\CRM;

use App\Models\Auth\User;
use App\Models\CRM\Lead;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.view_any');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leads.create');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.update');
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.delete');
    }

    public function restore(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.restore');
    }

    public function forceDelete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('leads.export');
    }

    public function qualify(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.qualify');
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.convert');
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.assign');
    }
}
