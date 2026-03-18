<?php

declare(strict_types=1);

namespace App\Policies\Purchasing;

use App\Models\Auth\User;
use App\Models\Purchasing\PurchaseRequest;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_requests.view_any');
    }

    public function view(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_requests.create');
    }

    public function update(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.update');
    }

    public function delete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.delete');
    }

    public function restore(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.restore');
    }

    public function forceDelete(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.force_delete');
    }

    public function submit(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.submit');
    }

    public function approve(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.approve');
    }

    public function reject(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.reject');
    }

    public function cancel(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.cancel');
    }

    public function convert(User $user, PurchaseRequest $purchaseRequest): bool
    {
        return $user->hasPermission('purchase_requests.convert');
    }
}
