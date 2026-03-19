<?php

use App\Http\Controllers\Inventory\StockInventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:stock_inventories.view_any')->get('stock-inventories', [StockInventoryController::class, 'index'])->name('stock-inventories.index');
    
    Route::middleware('permission:stock_inventories.create')->post('stock-inventories', [StockInventoryController::class, 'store'])->name('stock-inventories.store');
    
    Route::middleware('permission:stock_inventories.view')->get('stock-inventories/{stockInventory}', [StockInventoryController::class, 'show'])->name('stock-inventories.show');
    
    Route::middleware('permission:stock_inventories.update')->put('stock-inventories/{stockInventory}', [StockInventoryController::class, 'update'])->name('stock-inventories.update');
    
    Route::middleware('permission:stock_inventories.delete')->delete('stock-inventories/{stockInventory}', [StockInventoryController::class, 'destroy'])->name('stock-inventories.destroy');
    
    Route::middleware('permission:stock_inventories.create')->post('stock-inventories/{stockInventory}/start', [StockInventoryController::class, 'start'])->name('stock-inventories.start');
    
    Route::middleware('permission:stock_inventories.update')->post('stock-inventories/{stockInventory}/validate', [StockInventoryController::class, 'validate'])->name('stock-inventories.validate');

});
