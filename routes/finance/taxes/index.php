<?php

use App\Http\Controllers\Finance\TaxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:taxes.view_any')->get('taxes', [TaxController::class, 'index'])->name('taxes.index');
    
    Route::middleware('permission:taxes.create')->post('taxes', [TaxController::class, 'store'])->name('taxes.store');
    
    Route::middleware('permission:taxes.view')->get('taxes/{tax}', [TaxController::class, 'show'])->name('taxes.show');
    
    Route::middleware('permission:taxes.update')->put('taxes/{tax}', [TaxController::class, 'update'])->name('taxes.update');
    
    Route::middleware('permission:taxes.delete')->delete('taxes/{tax}', [TaxController::class, 'destroy'])->name('taxes.destroy');

});
