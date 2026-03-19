<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payments.view_any');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payments.create');
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.update');
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.delete');
    }

    public function confirm(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.confirm');
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $user->hasPermission('payments.cancel');
    }
}
