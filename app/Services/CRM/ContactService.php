<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactService
{
    /** Fields copied from a duplicate onto the kept contact when the kept one has no value. */
    private const MERGEABLE_FIELDS = [
        'email', 'phone', 'mobile', 'job_title', 'organization', 'customer_id',
        'supplier_id', 'address', 'city', 'country', 'source',
    ];

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): Contact
    {
        $data['created_by'] = auth()->id();

        return Contact::create($data);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($data);

        return $contact->fresh(['customer', 'supplier']);
    }

    public function delete(Contact $contact): bool
    {
        return (bool) $contact->delete();
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    /**
     * Case-insensitive search on name, email, phone and organization.
     */
    public function search(string $term, int $limit = 20): Collection
    {
        $like = '%' . $term . '%';

        return Contact::query()
            ->where(function ($query) use ($like) {
                foreach (['first_name', 'last_name', 'email', 'phone', 'mobile', 'organization'] as $column) {
                    $query->orWhereLike($column, $like);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    // ─── Merge ────────────────────────────────────────────────────────────────

    /**
     * Merge duplicate contacts into $target.
     *
     * Leads, opportunities and activities of the duplicates are re-pointed to
     * the target, empty target fields are filled from the duplicates, tags are
     * unioned and the duplicates are soft-deleted.
     *
     * @param  array<int>  $duplicateIds
     */
    public function merge(Contact $target, array $duplicateIds): Contact
    {
        $duplicateIds = array_values(array_unique(array_map('intval', $duplicateIds)));

        if (in_array($target->id, $duplicateIds, true)) {
            throw ValidationException::withMessages([
                'contact_ids' => 'A contact cannot be merged into itself.',
            ]);
        }

        // Global company scope guarantees we only see the current company's contacts.
        $duplicates = Contact::whereIn('id', $duplicateIds)->orderBy('id')->get();

        if ($duplicates->count() !== count($duplicateIds)) {
            throw ValidationException::withMessages([
                'contact_ids' => 'One or more contacts to merge were not found.',
            ]);
        }

        return DB::transaction(function () use ($target, $duplicates): Contact {
            $ids = $duplicates->pluck('id')->all();

            foreach ($duplicates as $duplicate) {
                foreach (self::MERGEABLE_FIELDS as $field) {
                    if (blank($target->{$field}) && filled($duplicate->{$field})) {
                        $target->{$field} = $duplicate->{$field};
                    }
                }

                if (filled($duplicate->notes)) {
                    $target->notes = trim(($target->notes ?? '') . "\n" . $duplicate->notes);
                }
            }

            $tags = $duplicates->pluck('tags')->prepend($target->tags)->filter()->flatten()->unique()->values()->all();
            $target->tags = $tags ?: null;
            $target->save();

            Lead::withTrashed()->whereIn('contact_id', $ids)->update(['contact_id' => $target->id]);
            Opportunity::withTrashed()->whereIn('contact_id', $ids)->update(['contact_id' => $target->id]);
            Activity::where('related_type', Contact::class)
                ->whereIn('related_id', $ids)
                ->update(['related_id' => $target->id]);

            Contact::whereIn('id', $ids)->delete();

            return $target->fresh(['customer', 'supplier']);
        });
    }
}
