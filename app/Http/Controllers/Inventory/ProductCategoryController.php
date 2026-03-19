<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductCategoryRequest;
use App\Http\Requests\Inventory\UpdateProductCategoryRequest;
use App\Http\Resources\Inventory\ProductCategoryResource;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\ProductCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        if ($request->boolean('tree')) {
            return response()->json($this->categoryService->getTree());
        }

        $categories = ProductCategory::with(['parent', 'children'])
            ->orderBy('sort_order')
            ->paginate($request->integer('per_page', 15));

        return ProductCategoryResource::collection($categories);
    }

    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', ProductCategory::class);

        $category = $this->categoryService->create($request->validated());

        return (new ProductCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function show(ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('view', $productCategory);

        $productCategory->load(['parent', 'children']);

        return new ProductCategoryResource($productCategory);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): ProductCategoryResource
    {
        $this->authorize('update', $productCategory);

        $category = $this->categoryService->update($productCategory, $request->validated());

        return new ProductCategoryResource($category);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $this->authorize('delete', $productCategory);

        try {
            $this->categoryService->delete($productCategory);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function tree(): JsonResponse
    {
        $this->authorize('viewAny', ProductCategory::class);

        return response()->json($this->categoryService->getTree());
    }
}
