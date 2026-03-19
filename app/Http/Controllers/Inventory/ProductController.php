<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Models\Inventory\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $filters  = $request->only(['search', 'category_id', 'type', 'is_active', 'is_sellable', 'is_purchasable', 'per_page']);
        $products = $this->productService->search($filters);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request->validated());

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        $product->load(['category']);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product = $this->productService->update($product, $request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return response()->json(null, 204);
    }

    public function lowStock(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getLowStockProducts();

        return response()->json([
            'data' => ProductResource::collection($products),
        ]);
    }

    public function stockLevels(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $stockLevels = $this->productService->getStockLevels($product);

        return response()->json($stockLevels);
    }
}
