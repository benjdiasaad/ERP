<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\PaymentTerm;

class PaymentTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('payment_terms.view_any');
    }

    public function view(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasPermission('payment_terms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('payment_terms.create');
    }

    public function update(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasPermission('payment_terms.update');
    }

    public function delete(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasPermission('payment_terms.delete');
    }

    public function restore(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasPermission('payment_terms.restore');
    }

    public function forceDelete(User $user, PaymentTerm $paymentTerm): bool
    {
        return $user->hasPermission('payment_terms.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('payment_terms.export');
    }
}
