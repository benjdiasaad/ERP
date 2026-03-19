<?php

use App\Http\Controllers\Caution\CautionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:cautions.view_any')->get('cautions', [CautionController::class, 'index'])->name('cautions.index');
    
    Route::middleware('permission:cautions.create')->post('cautions', [CautionController::class, 'store'])->name('cautions.store');
    
    Route::middleware('permission:cautions.view_any')->get('cautions/expiring', [CautionController::class, 'expiring'])->name('cautions.expiring');
    
    Route::middleware('permission:cautions.view_any')->get('cautions/stats', [CautionController::class, 'stats'])->name('cautions.stats');
    
    Route::middleware('permission:cautions.view')->get('cautions/{caution}', [CautionController::class, 'show'])->name('cautions.show');
    
    Route::middleware('permission:cautions.update')->put('cautions/{caution}', [CautionController::class, 'update'])->name('cautions.update');
    
    Route::middleware('permission:cautions.delete')->delete('cautions/{caution}', [CautionController::class, 'destroy'])->name('cautions.destroy');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/activate', [CautionController::class, 'activate'])->name('cautions.activate');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/partial-return', [CautionController::class, 'partialReturn'])->name('cautions.partial-return');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/full-return', [CautionController::class, 'fullReturn'])->name('cautions.full-return');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/extend', [CautionController::class, 'extend'])->name('cautions.extend');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/forfeit', [CautionController::class, 'forfeit'])->name('cautions.forfeit');
    
    Route::middleware('permission:cautions.update')->post('cautions/{caution}/cancel', [CautionController::class, 'cancel'])->name('cautions.cancel');

});
