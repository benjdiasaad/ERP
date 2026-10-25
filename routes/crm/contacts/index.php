<?php

use App\Http\Controllers\CRM\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:contacts.view_any')->get('contacts', [ContactController::class, 'index'])->name('contacts.index');

    Route::middleware('permission:contacts.create')->post('contacts', [ContactController::class, 'store'])->name('contacts.store');

    Route::middleware('permission:contacts.view_any')->get('contacts/search', [ContactController::class, 'search'])->name('contacts.search');

    Route::middleware('permission:contacts.view')->get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

    Route::middleware('permission:contacts.update')->put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');

    Route::middleware('permission:contacts.delete')->delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');

    Route::middleware('permission:contacts.merge')->post('contacts/{contact}/merge', [ContactController::class, 'merge'])->name('contacts.merge');

});
