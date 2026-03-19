<?php

declare(strict_types=1);

namespace App\Policies\Finance;

use App\Models\Auth\User;
use App\Models\Finance\JournalEntry;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('journal_entries.view_any');
    }

    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('journal_entries.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('journal_entries.create');
    }

    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('journal_entries.update');
    }

    public function delete(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('journal_entries.delete');
    }

    public function post(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('journal_entries.post');
    }

    public function cancel(User $user, JournalEntry $journalEntry): bool
    {
        return $user->hasPermission('journal_entries.cancel');
    }
}
