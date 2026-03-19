<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCategoryService
{
    public function create(array $data): ProductCategory
    {
        return DB::transaction(fn (): ProductCategory => ProductCategory::create($data));
    }

    public function update(ProductCategory $category, array $data): ProductCategory
    {
        return DB::transaction(function () use ($category, $data): ProductCategory {
            $category->update($data);

            return $category->fresh(['parent', 'children']);
        });
    }

    public function delete(ProductCategory $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has products assigned to it.',
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has sub-categories.',
            ]);
        }

        $category->delete();
    }

    public function getTree(): array
    {
        $categories = ProductCategory::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($categories);
    }

    public function getChildren(ProductCategory $category): Collection
    {
        return $category->children()->orderBy('sort_order')->get();
    }

    public function getFlat(): Collection
    {
        return ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function buildTree($categories): array
    {
        return $categories->map(function ($cat) {
            return array_merge($cat->toArray(), [
                'children' => $this->buildTree($cat->children),
            ]);
        })->toArray();
    }
}
