<?php

use App\Http\Controllers\CRM\OpportunityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:opportunities.view_any')->get('opportunities', [OpportunityController::class, 'index'])->name('opportunities.index');

    Route::middleware('permission:opportunities.create')->post('opportunities', [OpportunityController::class, 'store'])->name('opportunities.store');

    Route::middleware('permission:opportunities.view_any')->get('opportunities/pipeline', [OpportunityController::class, 'pipeline'])->name('opportunities.pipeline');

    Route::middleware('permission:opportunities.view_any')->get('opportunities/forecast', [OpportunityController::class, 'forecast'])->name('opportunities.forecast');

    Route::middleware('permission:opportunities.view')->get('opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show');

    Route::middleware('permission:opportunities.update')->put('opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update');

    Route::middleware('permission:opportunities.delete')->delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy');

    Route::middleware('permission:opportunities.advance')->post('opportunities/{opportunity}/advance', [OpportunityController::class, 'advance'])->name('opportunities.advance');

    Route::middleware('permission:opportunities.win')->post('opportunities/{opportunity}/win', [OpportunityController::class, 'win'])->name('opportunities.win');

    Route::middleware('permission:opportunities.lose')->post('opportunities/{opportunity}/lose', [OpportunityController::class, 'lose'])->name('opportunities.lose');

});
