<?php

use App\Http\Controllers\Caution\CautionTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:cautions.view_any')->get('caution-types', [CautionTypeController::class, 'index'])->name('caution-types.index');
    
    Route::middleware('permission:cautions.create')->post('caution-types', [CautionTypeController::class, 'store'])->name('caution-types.store');
    
    Route::middleware('permission:cautions.view')->get('caution-types/{cautionType}', [CautionTypeController::class, 'show'])->name('caution-types.show');
    
    Route::middleware('permission:cautions.update')->put('caution-types/{cautionType}', [CautionTypeController::class, 'update'])->name('caution-types.update');
    
    Route::middleware('permission:cautions.delete')->delete('caution-types/{cautionType}', [CautionTypeController::class, 'destroy'])->name('caution-types.destroy');
    
    Route::middleware('permission:cautions.restore')->post('caution-types/{cautionType}/restore', [CautionTypeController::class, 'restore'])->name('caution-types.restore');

});
