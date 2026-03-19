<?php

declare(strict_types=1);

namespace App\Services\Event;

use App\Models\Event\EventCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventCategoryService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new event category.
     */
    public function create(array $data): EventCategory
    {
        return DB::transaction(function () use ($data): EventCategory {
            $data['created_by'] = auth()->id();

            return EventCategory::create($data);
        });
    }

    /**
     * Update an event category.
     */
    public function update(EventCategory $category, array $data): EventCategory
    {
        return DB::transaction(function () use ($category, $data): EventCategory {
            $category->update($data);

            return $category->fresh();
        });
    }

    /**
     * Soft-delete an event category.
     */
    public function delete(EventCategory $category): bool
    {
        // Check if category has events
        if ($category->events()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete category with associated events.',
            ]);
        }

        return (bool) $category->delete();
    }
}
