<?php

use App\Http\Controllers\Finance\PaymentTermController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:payment_terms.view_any')->get('payment-terms', [PaymentTermController::class, 'index'])->name('payment-terms.index');
    
    Route::middleware('permission:payment_terms.create')->post('payment-terms', [PaymentTermController::class, 'store'])->name('payment-terms.store');
    
    Route::middleware('permission:payment_terms.view')->get('payment-terms/{paymentTerm}', [PaymentTermController::class, 'show'])->name('payment-terms.show');
    
    Route::middleware('permission:payment_terms.update')->put('payment-terms/{paymentTerm}', [PaymentTermController::class, 'update'])->name('payment-terms.update');
    
    Route::middleware('permission:payment_terms.delete')->delete('payment-terms/{paymentTerm}', [PaymentTermController::class, 'destroy'])->name('payment-terms.destroy');

});
