<?php

use App\Http\Controllers\Sales\CreditNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:credit_notes.view_any')->get('credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');

    Route::middleware('permission:credit_notes.create')->post('credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');

    Route::middleware('permission:credit_notes.view')->get('credit-notes/{creditNote}', [CreditNoteController::class, 'show'])->name('credit-notes.show');

    Route::middleware('permission:credit_notes.update')->put('credit-notes/{creditNote}', [CreditNoteController::class, 'update'])->name('credit-notes.update');

    Route::middleware('permission:credit_notes.delete')->delete('credit-notes/{creditNote}', [CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');

    Route::middleware('permission:credit_notes.confirm')->post('credit-notes/{creditNote}/confirm', [CreditNoteController::class, 'confirm'])->name('credit-notes.confirm');

    Route::middleware('permission:credit_notes.apply')->post('credit-notes/{creditNote}/apply', [CreditNoteController::class, 'apply'])->name('credit-notes.apply');

});
