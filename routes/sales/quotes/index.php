<?php

use App\Http\Controllers\Sales\QuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:quotes.view_any')->get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    
    Route::middleware('permission:quotes.create')->post('quotes', [QuoteController::class, 'store'])->name('quotes.store');
    
    Route::middleware('permission:quotes.view')->get('quotes/{quote}', [QuoteController::class, 'show'])->name('quotes.show');
    
    Route::middleware('permission:quotes.update')->put('quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
    
    Route::middleware('permission:quotes.delete')->delete('quotes/{quote}', [QuoteController::class, 'destroy'])->name('quotes.destroy');
    
    Route::middleware('permission:quotes.send')->post('quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
    
    Route::middleware('permission:quotes.update')->post('quotes/{quote}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
    
    Route::middleware('permission:quotes.update')->post('quotes/{quote}/reject', [QuoteController::class, 'reject'])->name('quotes.reject');
    
    Route::middleware('permission:quotes.create')->post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
    
    Route::middleware('permission:quotes.convert')->post('quotes/{quote}/convert-to-order', [QuoteController::class, 'convertToOrder'])->name('quotes.convert-to-order');
    
    Route::middleware('permission:quotes.view')->get('quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');

});
