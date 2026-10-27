<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreDepartmentRequest;
use App\Http\Requests\Personnel\StorePositionRequest;
use App\Http\Requests\Personnel\UpdateDepartmentRequest;
use App\Http\Requests\Personnel\UpdatePositionRequest;
use App\Models\Personnel\Department;
use App\Models\Personnel\Position;
use App\Services\Personnel\DepartmentService;
use App\Services\Personnel\PositionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departments and the positions inside them, managed from one page (dialogs).
 */
class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $departmentService,
        private readonly PositionService $positionService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Personnel/Departments/Index', [
            'departments' => Department::with(['parent:id,name', 'manager:id,first_name,last_name'])->withCount('personnels')->orderBy('name')->get()
                ->map(fn (Department $d) => [
                    ...$d->only(['id', 'name', 'code', 'description', 'parent_id', 'manager_id', 'is_active']),
                    'parent'          => $d->parent?->name,
                    'manager'         => $d->manager ? trim("{$d->manager->first_name} {$d->manager->last_name}") : null,
                    'personnels_count' => $d->personnels_count,
                ]),
            'positions'   => Position::with('department:id,name')->withCount('personnels')->orderBy('name')->get()
                ->map(fn (Position $p) => [
                    ...$p->only(['id', 'name', 'code', 'description', 'department_id', 'salary_min', 'salary_max', 'is_active']),
                    'department'       => $p->department?->name,
                    'personnels_count' => $p->personnels_count,
                ]),
            'managers'    => auth()->user()->currentCompany->users()->orderBy('last_name')->get(['users.id', 'first_name', 'last_name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => trim("{$u->first_name} {$u->last_name}")]),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = $this->departmentService->create($request->validated());

        return back()->with('success', "Département « {$department->name} » créé.");
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departmentService->update($department, $request->validated());

        return back()->with('success', 'Département mis à jour.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->personnels()->exists() || $department->positions()->exists() || $department->children()->exists()) {
            return back()->withErrors(['department' => 'Ce département contient du personnel, des postes ou des sous-départements.']);
        }

        $this->departmentService->delete($department);

        return back()->with('success', 'Département supprimé.');
    }

    public function storePosition(StorePositionRequest $request): RedirectResponse
    {
        $position = $this->positionService->create($request->validated());

        return back()->with('success', "Poste « {$position->name} » créé.");
    }

    public function updatePosition(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $this->positionService->update($position, $request->validated());

        return back()->with('success', 'Poste mis à jour.');
    }

    public function destroyPosition(Position $position): RedirectResponse
    {
        if ($position->personnels()->exists()) {
            return back()->withErrors(['position' => 'Ce poste est occupé par du personnel.']);
        }

        $this->positionService->delete($position);

        return back()->with('success', 'Poste supprimé.');
    }
}
