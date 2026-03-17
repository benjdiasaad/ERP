<?php

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Models\Auth\User;
use App\Models\Sales\Quote;

class QuotePolicy
{
    /**
     * Determine whether the user can view any quotes.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('quotes.view_any');
    }

    /**
     * Determine whether the user can view the quote.
     */
    public function view(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.view');
    }

    /**
     * Determine whether the user can create quotes.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('quotes.create');
    }

    /**
     * Determine whether the user can update the quote.
     */
    public function update(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.update');
    }

    /**
     * Determine whether the user can delete the quote.
     */
    public function delete(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.delete');
    }

    /**
     * Determine whether the user can restore the quote.
     */
    public function restore(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.restore');
    }

    /**
     * Determine whether the user can permanently delete the quote.
     */
    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.force_delete');
    }

    /**
     * Determine whether the user can send the quote to the customer.
     */
    public function send(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.send');
    }

    /**
     * Determine whether the user can accept the quote.
     */
    public function accept(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.accept');
    }

    /**
     * Determine whether the user can reject the quote.
     */
    public function reject(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.reject');
    }

    /**
     * Determine whether the user can convert the quote to a sales order.
     */
    public function convert(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.convert');
    }

    /**
     * Determine whether the user can duplicate the quote.
     */
    public function duplicate(User $user, Quote $quote): bool
    {
        return $user->hasPermission('quotes.duplicate');
    }
}
