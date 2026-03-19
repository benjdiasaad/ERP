<?php

use App\Http\Controllers\Inventory\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:warehouses.view_any')->get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    
    Route::middleware('permission:warehouses.create')->post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    
    Route::middleware('permission:warehouses.view')->get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    
    Route::middleware('permission:warehouses.update')->put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    
    Route::middleware('permission:warehouses.delete')->delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');

});
