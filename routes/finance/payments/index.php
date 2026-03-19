<?php

use App\Http\Controllers\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:payments.view_any')->get('payments', [PaymentController::class, 'index'])->name('payments.index');
    
    Route::middleware('permission:payments.create')->post('payments', [PaymentController::class, 'store'])->name('payments.store');
    
    Route::middleware('permission:payments.view')->get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    
    Route::middleware('permission:payments.update')->put('payments/{payment}', [PaymentController::class, 'update'])->name('payments.update');
    
    Route::middleware('permission:payments.delete')->delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::middleware('permission:payments.update')->post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    
    Route::middleware('permission:payments.update')->post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->name('payments.cancel');

});
