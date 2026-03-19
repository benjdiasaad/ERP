<?php

use App\Http\Controllers\Finance\BankAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:bank_accounts.view_any')->get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    
    Route::middleware('permission:bank_accounts.create')->post('bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    
    Route::middleware('permission:bank_accounts.view')->get('bank-accounts/{account}', [BankAccountController::class, 'show'])->name('bank-accounts.show');
    
    Route::middleware('permission:bank_accounts.update')->put('bank-accounts/{account}', [BankAccountController::class, 'update'])->name('bank-accounts.update');
    
    Route::middleware('permission:bank_accounts.delete')->delete('bank-accounts/{account}', [BankAccountController::class, 'destroy'])->name('bank-accounts.destroy');

});
