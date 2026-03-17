<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Department;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    /**
     * Create a new department.
     */
    public function create(array $data): Department
    {
        return DB::transaction(function () use ($data): Department {
            return Department::create($data);
        });
    }

    /**
     * Update an existing department.
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department->fresh(['parent', 'manager']);
    }

    /**
     * Soft-delete a department.
     */
    public function delete(Department $department): bool
    {
        return (bool) $department->delete();
    }

    /**
     * Return a hierarchical tree of departments.
     *
     * Root departments (no parent) are returned with their children
     * recursively loaded via the `children` relationship.
     */
    public function getTree(): Collection
    {
        $all = Department::with('children')->get();

        return $all->whereNull('parent_id')->values();
    }

    /**
     * Return all departments with a count of their personnel.
     */
    public function getWithPersonnelCount(): Collection
    {
        return Department::withCount('personnels')->get();
    }
}
