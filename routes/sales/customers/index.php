<?php

use App\Http\Controllers\Sales\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:customers.view_any')->get('customers', [CustomerController::class, 'index'])->name('customers.index');
    
    Route::middleware('permission:customers.create')->post('customers', [CustomerController::class, 'store'])->name('customers.store');
    
    Route::middleware('permission:customers.view_any')->get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    
    Route::middleware('permission:customers.view')->get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    
    Route::middleware('permission:customers.update')->put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    
    Route::middleware('permission:customers.delete')->delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    
    Route::middleware('permission:customers.view')->get('customers/{customer}/credit-info', [CustomerController::class, 'creditInfo'])->name('customers.credit-info');

});
