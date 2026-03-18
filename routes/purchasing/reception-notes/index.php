<?php

use App\Http\Controllers\Purchasing\ReceptionNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:reception_notes.view_any')->get('reception-notes', [ReceptionNoteController::class, 'index'])->name('reception-notes.index');

    Route::middleware('permission:reception_notes.create')->post('reception-notes', [ReceptionNoteController::class, 'store'])->name('reception-notes.store');

    Route::middleware('permission:reception_notes.view')->get('reception-notes/{receptionNote}', [ReceptionNoteController::class, 'show'])->name('reception-notes.show');

    Route::middleware('permission:reception_notes.update')->put('reception-notes/{receptionNote}', [ReceptionNoteController::class, 'update'])->name('reception-notes.update');

    Route::middleware('permission:reception_notes.delete')->delete('reception-notes/{receptionNote}', [ReceptionNoteController::class, 'destroy'])->name('reception-notes.destroy');

    Route::middleware('permission:reception_notes.confirm')->post('reception-notes/{receptionNote}/confirm', [ReceptionNoteController::class, 'confirm'])->name('reception-notes.confirm');

    Route::middleware('permission:reception_notes.cancel')->post('reception-notes/{receptionNote}/cancel', [ReceptionNoteController::class, 'cancel'])->name('reception-notes.cancel');

});
