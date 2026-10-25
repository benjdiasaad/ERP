<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\CRM\Concerns;

use App\Models\Auth\User;
use App\Models\CRM\Contact;
use App\Models\Sales\Customer;

/**
 * Select options shared by the CRM screens, all limited to the current company.
 */
trait ProvidesCrmOptions
{
    /** Users of the current company, for "assigned to" fields. */
    protected function userOptions(): array
    {
        $companyId = auth()->user()->current_company_id;

        return User::query()
            ->whereHas('companies', fn ($query) => $query->where('companies.id', $companyId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['value' => $user->id, 'label' => $user->name])
            ->all();
    }

    protected function contactOptions(): array
    {
        return Contact::query()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'organization'])
            ->map(fn (Contact $contact) => [
                'value' => $contact->id,
                'label' => $contact->full_name . ($contact->organization ? " ({$contact->organization})" : ''),
            ])
            ->all();
    }

    protected function customerOptions(): array
    {
        return Customer::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Customer $customer) => ['value' => $customer->id, 'label' => $customer->name])
            ->all();
    }
}
