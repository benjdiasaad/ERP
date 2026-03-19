<?php

use App\Http\Controllers\Sales\DeliveryNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:delivery_notes.view_any')->get('delivery-notes', [DeliveryNoteController::class, 'index'])->name('delivery-notes.index');

    Route::middleware('permission:delivery_notes.create')->post('delivery-notes', [DeliveryNoteController::class, 'store'])->name('delivery-notes.store');

    Route::middleware('permission:delivery_notes.view')->get('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show'])->name('delivery-notes.show');

    Route::middleware('permission:delivery_notes.update')->put('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'update'])->name('delivery-notes.update');

    Route::middleware('permission:delivery_notes.delete')->delete('delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'destroy'])->name('delivery-notes.destroy');

    Route::middleware('permission:delivery_notes.ship')->post('delivery-notes/{deliveryNote}/ship', [DeliveryNoteController::class, 'ship'])->name('delivery-notes.ship');

    Route::middleware('permission:delivery_notes.deliver')->post('delivery-notes/{deliveryNote}/deliver', [DeliveryNoteController::class, 'deliver'])->name('delivery-notes.deliver');

    Route::middleware('permission:delivery_notes.return')->post('delivery-notes/{deliveryNote}/return', [DeliveryNoteController::class, 'return'])->name('delivery-notes.return');

});
