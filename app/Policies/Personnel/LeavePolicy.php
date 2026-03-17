<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Leave;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leaves.view_any');
    }

    public function view(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.view')
            && $leave->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('leaves.create');
    }

    public function update(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.update')
            && $leave->company_id === $user->current_company_id;
    }

    public function delete(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.delete')
            && $leave->company_id === $user->current_company_id;
    }

    public function restore(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.restore')
            && $leave->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.force_delete')
            && $leave->company_id === $user->current_company_id;
    }

    public function approve(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.approve')
            && $leave->company_id === $user->current_company_id;
    }

    public function reject(User $user, Leave $leave): bool
    {
        return $user->hasPermission('leaves.approve')
            && $leave->company_id === $user->current_company_id;
    }
}
