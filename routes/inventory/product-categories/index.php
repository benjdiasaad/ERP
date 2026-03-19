<?php

use App\Http\Controllers\Inventory\ProductCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::middleware('permission:product_categories.view_any')->get('product-categories/tree', [ProductCategoryController::class, 'tree'])->name('product-categories.tree');
    Route::middleware('permission:product_categories.view_any')->get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
    Route::middleware('permission:product_categories.create')->post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
    Route::middleware('permission:product_categories.view')->get('product-categories/{productCategory}', [ProductCategoryController::class, 'show'])->name('product-categories.show');
    Route::middleware('permission:product_categories.update')->put('product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
    Route::middleware('permission:product_categories.delete')->delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
});
