<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Position;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PositionService
{
    /**
     * Create a new position.
     */
    public function create(array $data): Position
    {
        return DB::transaction(function () use ($data): Position {
            return Position::create($data);
        });
    }

    /**
     * Update an existing position.
     */
    public function update(Position $position, array $data): Position
    {
        $position->update($data);

        return $position->fresh(['department']);
    }

    /**
     * Soft-delete a position.
     */
    public function delete(Position $position): bool
    {
        return (bool) $position->delete();
    }

    /**
     * Get all positions belonging to a department.
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return Position::where('department_id', $departmentId)->get();
    }
}
