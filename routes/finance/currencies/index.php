<?php

use App\Http\Controllers\Finance\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:currencies.view_any')->get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
    
    Route::middleware('permission:currencies.create')->post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
    
    Route::middleware('permission:currencies.view')->get('currencies/{currency}', [CurrencyController::class, 'show'])->name('currencies.show');
    
    Route::middleware('permission:currencies.update')->put('currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
    
    Route::middleware('permission:currencies.delete')->delete('currencies/{currency}', [CurrencyController::class, 'destroy'])->name('currencies.destroy');

});
