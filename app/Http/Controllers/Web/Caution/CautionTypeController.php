<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Caution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Caution\StoreCautionTypeRequest;
use App\Http\Requests\Caution\UpdateCautionTypeRequest;
use App\Http\Resources\Caution\CautionTypeResource;
use App\Models\Caution\CautionType;
use App\Services\Caution\CautionTypeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Caution types are managed from a single page (create / edit in a dialog).
 */
class CautionTypeController extends Controller
{
    public function __construct(
        private readonly CautionTypeService $typeService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', CautionType::class);

        return Inertia::render('Caution/Types/Index', [
            'types' => CautionTypeResource::collection(CautionType::withCount('cautions')->orderBy('name')->get()),
        ]);
    }

    public function store(StoreCautionTypeRequest $request): RedirectResponse
    {
        $this->authorize('create', CautionType::class);

        $type = $this->typeService->create($request->validated());

        return back()->with('success', "Type « {$type->name} » créé.");
    }

    public function update(UpdateCautionTypeRequest $request, CautionType $type): RedirectResponse
    {
        $this->authorize('update', $type);

        $this->typeService->update($type, $request->validated());

        return back()->with('success', 'Type mis à jour.');
    }

    public function destroy(CautionType $type): RedirectResponse
    {
        $this->authorize('delete', $type);

        $this->typeService->delete($type);

        return back()->with('success', 'Type supprimé.');
    }
}
