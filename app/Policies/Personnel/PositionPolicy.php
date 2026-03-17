<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Position;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('positions.view_any');
    }

    public function view(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.view')
            && $position->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('positions.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.update')
            && $position->company_id === $user->current_company_id;
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.delete')
            && $position->company_id === $user->current_company_id;
    }

    public function restore(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.restore')
            && $position->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Position $position): bool
    {
        return $user->hasPermission('positions.force_delete')
            && $position->company_id === $user->current_company_id;
    }
}
