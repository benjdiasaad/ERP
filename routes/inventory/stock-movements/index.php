<?php

use App\Http\Controllers\Inventory\StockMovementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:stock_movements.view_any')->get('stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
    
    Route::middleware('permission:stock_movements.create')->post('stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
    
    Route::middleware('permission:stock_movements.view_any')->get('stock-movements/report', [StockMovementController::class, 'report'])->name('stock-movements.report');

    Route::middleware('permission:stock_movements.view')->get('stock-movements/{stockMovement}', [StockMovementController::class, 'show'])->name('stock-movements.show');
    
    
    
    Route::middleware('permission:stock_movements.transfer')->post('stock-movements/transfer', [StockMovementController::class, 'transfer'])->name('stock-movements.transfer');
    

});
