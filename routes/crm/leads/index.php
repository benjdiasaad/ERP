<?php

use App\Http\Controllers\CRM\LeadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:leads.view_any')->get('leads', [LeadController::class, 'index'])->name('leads.index');

    Route::middleware('permission:leads.create')->post('leads', [LeadController::class, 'store'])->name('leads.store');

    Route::middleware('permission:leads.view_any')->get('leads/pipeline', [LeadController::class, 'pipeline'])->name('leads.pipeline');

    Route::middleware('permission:leads.view')->get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');

    Route::middleware('permission:leads.update')->put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');

    Route::middleware('permission:leads.delete')->delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

    Route::middleware('permission:leads.qualify')->post('leads/{lead}/qualify', [LeadController::class, 'qualify'])->name('leads.qualify');

    Route::middleware('permission:leads.convert')->post('leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert');

    Route::middleware('permission:leads.assign')->post('leads/{lead}/assign', [LeadController::class, 'assign'])->name('leads.assign');

});
