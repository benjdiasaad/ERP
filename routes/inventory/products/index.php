<?php

use App\Http\Controllers\Inventory\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::middleware('permission:products.view_any')->get('products', [ProductController::class, 'index'])->name('products.index');
    Route::middleware('permission:products.create')->post('products', [ProductController::class, 'store'])->name('products.store');
    Route::middleware('permission:products.view_any')->get('products/low-stock', [ProductController::class, 'lowStock'])->name('products.low-stock');
    Route::middleware('permission:products.view')->get('products/{product}/stock-levels', [ProductController::class, 'stockLevels'])->name('products.stock-levels');
    Route::middleware('permission:products.view')->get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::middleware('permission:products.update')->put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::middleware('permission:products.delete')->delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
});
