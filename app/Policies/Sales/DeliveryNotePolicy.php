<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\DeliveryNote;

class DeliveryNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('delivery_notes.view_any');
    }

    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('delivery_notes.create');
    }

    public function update(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.update');
    }

    public function delete(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.delete');
    }

    public function restore(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.restore');
    }

    public function forceDelete(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.force_delete');
    }

    public function ship(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.ship');
    }

    public function deliver(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.deliver');
    }

    public function return(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.return');
    }

    public function print(User $user, DeliveryNote $deliveryNote): bool
    {
        return $user->hasPermission('delivery_notes.print');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('delivery_notes.export');
    }
}
