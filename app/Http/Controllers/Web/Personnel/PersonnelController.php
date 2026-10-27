<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StorePersonnelRequest;
use App\Http\Requests\Personnel\UpdatePersonnelRequest;
use App\Http\Resources\Personnel\ContractResource;
use App\Http\Resources\Personnel\LeaveResource;
use App\Http\Resources\Personnel\PersonnelResource;
use App\Models\Personnel\Department;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use App\Services\Personnel\PersonnelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonnelController extends Controller
{
    public function __construct(
        private readonly PersonnelService $personnelService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'department_id', 'status']);

        $personnels = Personnel::query()
            ->with(['department:id,name', 'position:id,name'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('first_name', $like)->orWhereLike('last_name', $like)
                    ->orWhereLike('matricule', $like)->orWhereLike('email', $like));
            })
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Personnel/Personnels/Index', [
            'personnels'  => PersonnelResource::collection($personnels),
            'departments' => $this->departmentOptions(),
            'filters'     => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Personnel/Personnels/Create', $this->formOptions());
    }

    public function store(StorePersonnelRequest $request): RedirectResponse
    {
        $personnel = $this->personnelService->create($request->validated());

        return redirect()->route('organisation.personnels.show', $personnel)->with('success', "{$personnel->first_name} {$personnel->last_name} ajouté(e) au personnel.");
    }

    public function show(Personnel $personnel): Response
    {
        $personnel->load(['department:id,name', 'position:id,name']);
        $user = auth()->user();

        return Inertia::render('Personnel/Personnels/Show', [
            'personnel' => new PersonnelResource($personnel),
            'contracts' => $user->hasPermission('contracts.view_any')
                ? ContractResource::collection($personnel->contracts()->latest('start_date')->get())
                : null,
            'leaves'    => $user->hasPermission('leaves.view_any')
                ? LeaveResource::collection($personnel->leaves()->latest('start_date')->limit(10)->get())
                : null,
        ]);
    }

    public function edit(Personnel $personnel): Response
    {
        return Inertia::render('Personnel/Personnels/Edit', [
            'personnel' => new PersonnelResource($personnel),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdatePersonnelRequest $request, Personnel $personnel): RedirectResponse
    {
        $this->personnelService->update($personnel, $request->validated());

        return redirect()->route('organisation.personnels.show', $personnel)->with('success', 'Fiche mise à jour.');
    }

    public function destroy(Personnel $personnel): RedirectResponse
    {
        $this->personnelService->delete($personnel);

        return redirect()->route('organisation.personnels.index')->with('success', 'Fiche supprimée.');
    }

    private function departmentOptions()
    {
        return Department::orderBy('name')->get(['id', 'name'])->map(fn (Department $d) => ['value' => $d->id, 'label' => $d->name]);
    }

    private function formOptions(): array
    {
        return [
            'departments' => $this->departmentOptions(),
            'positions'   => Position::orderBy('name')->get(['id', 'name', 'department_id'])
                ->map(fn (Position $p) => ['value' => $p->id, 'label' => $p->name, 'department_id' => $p->department_id]),
        ];
    }
}
