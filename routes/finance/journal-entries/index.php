<?php

use App\Http\Controllers\Finance\JournalEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:journal_entries.view_any')->get('journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
    
    Route::middleware('permission:journal_entries.create')->post('journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
    
    Route::middleware('permission:journal_entries.view')->get('journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');
    
    Route::middleware('permission:journal_entries.update')->put('journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])->name('journal-entries.update');
    
    Route::middleware('permission:journal_entries.delete')->delete('journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('journal-entries.destroy');

    Route::middleware('permission:journal_entries.update')->post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
    
    Route::middleware('permission:journal_entries.update')->post('journal-entries/{journalEntry}/cancel', [JournalEntryController::class, 'cancel'])->name('journal-entries.cancel');

});
