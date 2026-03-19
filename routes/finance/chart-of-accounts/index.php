<?php

use App\Http\Controllers\Finance\ChartOfAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:chart_of_accounts.view_any')->get('chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
    
    Route::middleware('permission:chart_of_accounts.create')->post('chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
    
    Route::middleware('permission:chart_of_accounts.view')->get('chart-of-accounts/tree', [ChartOfAccountController::class, 'tree'])->name('chart-of-accounts.tree');
    
    Route::middleware('permission:chart_of_accounts.view')->get('chart-of-accounts/{account}', [ChartOfAccountController::class, 'show'])->name('chart-of-accounts.show');
    
    Route::middleware('permission:chart_of_accounts.view')->get('chart-of-accounts/{account}/balance', [ChartOfAccountController::class, 'balance'])->name('chart-of-accounts.balance');
    
    Route::middleware('permission:chart_of_accounts.update')->put('chart-of-accounts/{account}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
    
    Route::middleware('permission:chart_of_accounts.delete')->delete('chart-of-accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');

});
