<?php

declare(strict_types=1);

namespace App\Services\Caution;

use App\Models\Caution\CautionType;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CautionTypeService
{
    /**
     * List / filter caution types with pagination.
     *
     * Supported filters: search (string), paginate (int, default 15).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = CautionType::query();

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('description', $like, "%{$term}%");
            });
        }

        $perPage = $filters['paginate'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a new caution type record.
     */
    public function create(array $data): CautionType
    {
        return DB::transaction(function () use ($data): CautionType {
            return CautionType::create($data);
        });
    }

    /**
     * Update an existing caution type record.
     */
    public function update(CautionType $cautionType, array $data): CautionType
    {
        return DB::transaction(function () use ($cautionType, $data): CautionType {
            $cautionType->update($data);

            return $cautionType->fresh();
        });
    }

    /**
     * Soft-delete a caution type record.
     */
    public function delete(CautionType $cautionType): void
    {
        $cautionType->delete();
    }

    /**
     * Search caution types by a term (name, description).
     */
    public function search(string $term): Collection
    {
        $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return CautionType::query()
            ->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('description', $like, "%{$term}%");
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all caution types (for dropdowns, etc.).
     */
    public function all(): Collection
    {
        return CautionType::query()
            ->orderBy('name')
            ->get();
    }
}
