<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PersonnelService
{
    /**
     * Create a new personnel record.
     */
    public function create(array $data): Personnel
    {
        return DB::transaction(function () use ($data): Personnel {
            if (empty($data['matricule'])) {
                $data['matricule'] = $this->generateMatricule();
            }

            $data['created_by'] = auth()->id();

            return Personnel::create($data);
        });
    }

    /**
     * Update an existing personnel record.
     */
    public function update(Personnel $personnel, array $data): Personnel
    {
        return DB::transaction(function () use ($personnel, $data): Personnel {
            $personnel->update($data);

            return $personnel->fresh(['department', 'position', 'user']);
        });
    }

    /**
     * Soft-delete a personnel record.
     */
    public function delete(Personnel $personnel): bool
    {
        return (bool) $personnel->delete();
    }

    /**
     * Link a system user to this personnel record.
     */
    public function linkUser(Personnel $personnel, int $userId): Personnel
    {
        $personnel->update(['user_id' => $userId]);

        return $personnel->fresh(['user']);
    }

    /**
     * Remove the user link from this personnel record.
     */
    public function unlinkUser(Personnel $personnel): Personnel
    {
        $personnel->update(['user_id' => null]);

        return $personnel->fresh();
    }

    /**
     * Get all personnel belonging to a department.
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return Personnel::where('department_id', $departmentId)->get();
    }

    /**
     * Get all personnel holding a specific position.
     */
    public function getByPosition(int $positionId): Collection
    {
        return Personnel::where('position_id', $positionId)->get();
    }

    /**
     * Search / filter personnel with pagination.
     *
     * Supported filters: name, department_id, position_id, status, employment_type.
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Personnel::query()
            ->with(['department', 'position']);

        if (!empty($filters['name'])) {
            $name = $filters['name'];
            $query->where(function ($q) use ($name) {
                $q->where('first_name', 'ilike', "%{$name}%")
                  ->orWhere('last_name', 'ilike', "%{$name}%")
                  ->orWhere('matricule', 'ilike', "%{$name}%");
            });
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['position_id'])) {
            $query->where('position_id', $filters['position_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('last_name')->orderBy('first_name')->paginate($perPage);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateMatricule(): string
    {
        $year  = now()->year;
        $count = Personnel::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('EMP-%d-%05d', $year, $count);
    }
}
