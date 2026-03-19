<?php

use App\Http\Controllers\Event\EventCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:event_categories.view_any')->get('event-categories', [EventCategoryController::class, 'index'])->name('event-categories.index');
    
    Route::middleware('permission:event_categories.create')->post('event-categories', [EventCategoryController::class, 'store'])->name('event-categories.store');
    
    Route::middleware('permission:event_categories.view')->get('event-categories/{eventCategory}', [EventCategoryController::class, 'show'])->name('event-categories.show');
    
    Route::middleware('permission:event_categories.update')->put('event-categories/{eventCategory}', [EventCategoryController::class, 'update'])->name('event-categories.update');
    
    Route::middleware('permission:event_categories.delete')->delete('event-categories/{eventCategory}', [EventCategoryController::class, 'destroy'])->name('event-categories.destroy');

});
