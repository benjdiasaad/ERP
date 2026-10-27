<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventCategoryRequest;
use App\Http\Requests\Event\UpdateEventCategoryRequest;
use App\Http\Resources\Event\EventCategoryResource;
use App\Models\Event\EventCategory;
use App\Services\Event\EventCategoryService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event categories are managed from a single page (create / edit in a dialog).
 */
class EventCategoryController extends Controller
{
    public function __construct(
        private readonly EventCategoryService $categoryService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Event/Categories/Index', [
            'categories' => EventCategoryResource::collection(EventCategory::withCount('events')->orderBy('name')->get()),
        ]);
    }

    public function store(StoreEventCategoryRequest $request): RedirectResponse
    {
        $category = $this->categoryService->create($request->validated());

        return back()->with('success', "Catégorie « {$category->name} » créée.");
    }

    public function update(UpdateEventCategoryRequest $request, EventCategory $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(EventCategory $category): RedirectResponse
    {
        if ($category->events()->withTrashed()->exists()) {
            return back()->withErrors(['category' => 'Cette catégorie est utilisée par des événements.']);
        }

        $this->categoryService->delete($category);

        return back()->with('success', 'Catégorie supprimée.');
    }
}
