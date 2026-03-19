<?php

use App\Http\Controllers\Inventory\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:stock_movements.view_any')->get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    
    Route::middleware('permission:stock_movements.create')->post('stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
    
    Route::middleware('permission:stock_movements.view')->get('stock-movements/{stockMovement}', [StockMovementController::class, 'show'])->name('stock-movements.show');
    
    Route::middleware('permission:stock_movements.update')->put('stock-movements/{stockMovement}', [StockMovementController::class, 'update'])->name('stock-movements.update');
    
    Route::middleware('permission:stock_movements.delete')->delete('stock-movements/{stockMovement}', [StockMovementController::class, 'destroy'])->name('stock-movements.destroy');
    
    Route::middleware('permission:stock_movements.create')->post('stock-movements/transfer', [StockMovementController::class, 'transfer'])->name('stock-movements.transfer');
    
    Route::middleware('permission:stock_movements.view_any')->get('stock-movements/report', [StockMovementController::class, 'report'])->name('stock-movements.report');

});
