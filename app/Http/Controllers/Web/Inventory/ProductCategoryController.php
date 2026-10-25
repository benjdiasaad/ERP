<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductCategoryRequest;
use App\Http\Requests\Inventory\UpdateProductCategoryRequest;
use App\Http\Resources\Inventory\ProductCategoryResource;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\ProductCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Categories are managed from a single page (create / edit in a dialog).
 */
class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', ProductCategory::class);

        $categories = ProductCategory::with('parent:id,name')->withCount('products')->orderBy('name')->get();

        return Inertia::render('Inventory/Categories/Index', [
            'categories' => ProductCategoryResource::collection($categories),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ProductCategory::class);

        $category = $this->categoryService->create($request->validated());

        return back()->with('success', "Catégorie « {$category->name} » créée.");
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);

        if ((int) $request->input('parent_id') === $category->id) {
            throw ValidationException::withMessages(['parent_id' => 'Une catégorie ne peut pas être sa propre parente.']);
        }

        $this->categoryService->update($category, $request->validated());

        return back()->with('success', 'Catégorie mise à jour.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->delete($category);

        return back()->with('success', 'Catégorie supprimée.');
    }
}
