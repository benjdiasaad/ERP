<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Http\Resources\Inventory\ProductResource;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $filters = $request->only(['search', 'category_id', 'type', 'status']);

        $products = Product::query()
            ->with('category:id,name')
            ->withSum('stockLevels as stock_on_hand', 'quantity_on_hand')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('name', $like)->orWhereLike('code', $like)->orWhereLike('barcode', $like));
            })
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Before the resource collection wraps the models.
        $stock = $products->getCollection()->mapWithKeys(function (Product $product) {
            $onHand = (float) ($product->stock_on_hand ?? 0);

            return [$product->id => [
                'on_hand' => $onHand,
                'low'     => $product->is_stockable && (float) $product->reorder_point > 0 && $onHand <= (float) $product->reorder_point,
            ]];
        });

        return Inertia::render('Inventory/Products/Index', [
            'products'   => ProductResource::collection($products),
            'stock'      => $stock,
            'categories' => $this->categoryOptions(),
            'filters'    => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Inventory/Products/Create', ['categories' => $this->categoryOptions()]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->create($request->validated());

        return redirect()->route('stock.products.show', $product)->with('success', "Produit {$product->code} créé.");
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        $product->load('category:id,name');
        $user = auth()->user();

        return Inertia::render('Inventory/Products/Show', [
            'product'   => new ProductResource($product),
            'levels'    => $product->stockLevels()->with('warehouse:id,code,name')->get()
                ->map(fn ($level) => [
                    'warehouse' => $level->warehouse?->only(['id', 'code', 'name']),
                    'on_hand'   => (float) $level->quantity_on_hand,
                    'reserved'  => (float) $level->quantity_reserved,
                    'last_movement_at' => $level->last_movement_at,
                ]),
            'movements' => $user->hasPermission('stock_movements.view_any')
                ? StockMovementResource::collection($product->stockMovements()->with('warehouse:id,code,name')->latest('id')->limit(10)->get())
                : null,
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Inventory/Products/Edit', [
            'product'    => new ProductResource($product),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->update($product, $request->validated());

        return redirect()->route('stock.products.show', $product)->with('success', 'Produit mis à jour.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->delete($product);

        return redirect()->route('stock.products.index')->with('success', 'Produit supprimé.');
    }

    private function categoryOptions()
    {
        return ProductCategory::orderBy('name')->get(['id', 'name'])
            ->map(fn (ProductCategory $c) => ['value' => $c->id, 'label' => $c->name]);
    }
}
