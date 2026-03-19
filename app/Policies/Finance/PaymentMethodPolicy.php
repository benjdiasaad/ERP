<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\PaymentMethod;

class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payment_methods.view_any');
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission('payment_methods.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payment_methods.create');
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission('payment_methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission('payment_methods.delete');
    }

    public function restore(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission('payment_methods.restore');
    }

    public function forceDelete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission('payment_methods.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('payment_methods.export');
    }
}
