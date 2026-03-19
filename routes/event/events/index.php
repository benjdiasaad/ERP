<?php

use App\Http\Controllers\Event\EventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:events.view_any')->get('events', [EventController::class, 'index'])->name('events.index');
    
    Route::middleware('permission:events.create')->post('events', [EventController::class, 'store'])->name('events.store');
    
    Route::middleware('permission:events.view_any')->get('events/upcoming', [EventController::class, 'upcoming'])->name('events.upcoming');
    
    Route::middleware('permission:events.view_any')->get('events/calendar', [EventController::class, 'calendar'])->name('events.calendar');
    
    Route::middleware('permission:events.view')->get('events/{event}', [EventController::class, 'show'])->name('events.show');
    
    Route::middleware('permission:events.update')->put('events/{event}', [EventController::class, 'update'])->name('events.update');
    
    Route::middleware('permission:events.delete')->delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    
    Route::middleware('permission:events.update')->post('events/{event}/confirm', [EventController::class, 'confirm'])->name('events.confirm');
    
    Route::middleware('permission:events.update')->post('events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
    
    Route::middleware('permission:events.update')->post('events/{event}/complete', [EventController::class, 'complete'])->name('events.complete');

});
