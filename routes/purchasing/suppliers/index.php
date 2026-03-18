<?php

use App\Http\Controllers\Purchasing\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:suppliers.view_any')->get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');

    Route::middleware('permission:suppliers.create')->post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');

    Route::middleware('permission:suppliers.view_any')->get('suppliers/search', [SupplierController::class, 'search'])->name('suppliers.search');

    Route::middleware('permission:suppliers.view')->get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');

    Route::middleware('permission:suppliers.update')->put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::middleware('permission:suppliers.delete')->delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

});
