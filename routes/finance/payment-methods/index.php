<?php

use App\Http\Controllers\Finance\PaymentMethodController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:payment_methods.view_any')->get('payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    
    Route::middleware('permission:payment_methods.create')->post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    
    Route::middleware('permission:payment_methods.view')->get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show'])->name('payment-methods.show');
    
    Route::middleware('permission:payment_methods.update')->put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    
    Route::middleware('permission:payment_methods.delete')->delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

});
