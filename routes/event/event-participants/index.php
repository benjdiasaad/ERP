<?php

use App\Http\Controllers\Event\EventParticipantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:events.view')->get('events/{event}/participants', [EventParticipantController::class, 'index'])->name('events.participants.index');
    
    Route::middleware('permission:events.update')->post('events/{event}/participants', [EventParticipantController::class, 'store'])->name('events.participants.store');
    
    Route::middleware('permission:events.update')->post('events/{event}/participants/bulk-invite', [EventParticipantController::class, 'bulkInvite'])->name('events.participants.bulk-invite');
    
    Route::middleware('permission:events.view')->get('events/{event}/participants/{participant}', [EventParticipantController::class, 'show'])->name('events.participants.show');
    
    Route::middleware('permission:events.update')->put('events/{event}/participants/{participant}', [EventParticipantController::class, 'update'])->name('events.participants.update');
    
    Route::middleware('permission:events.update')->delete('events/{event}/participants/{participant}', [EventParticipantController::class, 'destroy'])->name('events.participants.destroy');
    
    Route::middleware('permission:events.update')->post('events/{event}/participants/{participant}/confirm', [EventParticipantController::class, 'confirm'])->name('events.participants.confirm');
    
    Route::middleware('permission:events.update')->post('events/{event}/participants/{participant}/decline', [EventParticipantController::class, 'decline'])->name('events.participants.decline');

});
