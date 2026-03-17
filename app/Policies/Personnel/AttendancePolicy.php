<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Attendance;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendances.view_any');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendances.view')
            && $attendance->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendances.create');
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendances.update')
            && $attendance->company_id === $user->current_company_id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendances.delete')
            && $attendance->company_id === $user->current_company_id;
    }

    public function restore(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendances.restore')
            && $attendance->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendances.force_delete')
            && $attendance->company_id === $user->current_company_id;
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendances.export');
    }
}
