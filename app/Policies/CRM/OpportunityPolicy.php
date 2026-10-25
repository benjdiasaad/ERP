<?php

declare(strict_types=1);

namespace App\Policies\CRM;

use App\Models\Auth\User;
use App\Models\CRM\Opportunity;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('opportunities.view_any');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('opportunities.create');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.update');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.delete');
    }

    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.restore');
    }

    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('opportunities.export');
    }

    public function advance(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.advance');
    }

    public function win(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.win');
    }

    public function lose(User $user, Opportunity $opportunity): bool
    {
        return $user->hasPermission('opportunities.lose');
    }
}
