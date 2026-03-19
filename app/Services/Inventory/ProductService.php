<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            if (empty($data['code'])) {
                $data['code'] = $this->generateCode();
            }

            return Product::create($data);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update($data);

            return $product->fresh(['category']);
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function search(array $filters): LengthAwarePaginator
    {
        $query = Product::query()->with(['category']);

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('name', $like, "%{$term}%")
                  ->orWhere('code', $like, "%{$term}%")
                  ->orWhere('barcode', $like, "%{$term}%")
                  ->orWhere('description', $like, "%{$term}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (isset($filters['is_sellable'])) {
            $query->where('is_sellable', (bool) $filters['is_sellable']);
        }

        if (isset($filters['is_purchasable'])) {
            $query->where('is_purchasable', (bool) $filters['is_purchasable']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Check stock level for a product.
     * current_stock is 0 as a placeholder until Task 17 (StockLevel table).
     *
     * @return array{current_stock: float, min_stock_level: float, is_low_stock: bool}
     */
    public function checkStockLevel(Product $product): array
    {
        $currentStock   = 0.0; // Placeholder until Task 17
        $minStockLevel  = (float) $product->min_stock_level;
        $isLowStock     = $minStockLevel > 0 && $currentStock <= $minStockLevel;

        return [
            'current_stock'   => $currentStock,
            'min_stock_level' => $minStockLevel,
            'is_low_stock'    => $isLowStock,
        ];
    }

    /**
     * Get products with low stock alert configured (min_stock_level > 0).
     * Placeholder until Task 17 provides actual stock levels.
     */
    public function getLowStockProducts(): Collection
    {
        return Product::query()
            ->where('is_stockable', true)
            ->where('min_stock_level', '>', 0)
            ->with(['category'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get stock levels for a specific product.
     * Returns empty stock_levels array as placeholder until Task 17 (StockLevel table).
     *
     * @return array{product_id: int, stock_levels: array}
     */
    public function getStockLevels(Product $product): array
    {
        return [
            'product_id'   => $product->id,
            'stock_levels' => [], // Placeholder until Task 17 provides StockLevel table
        ];
    }

    public function generateCode(): string
    {
        $year  = now()->format('Y');
        $count = Product::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('PROD-%s-%05d', $year, $count);
    }
}
