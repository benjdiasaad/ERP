<?php

use App\Http\Controllers\CRM\ActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:activities.view_any')->get('activities', [ActivityController::class, 'index'])->name('activities.index');

    Route::middleware('permission:activities.create')->post('activities', [ActivityController::class, 'store'])->name('activities.store');

    Route::middleware('permission:activities.view_any')->get('activities/overdue', [ActivityController::class, 'overdue'])->name('activities.overdue');

    Route::middleware('permission:activities.view_any')->get('activities/upcoming', [ActivityController::class, 'upcoming'])->name('activities.upcoming');

    Route::middleware('permission:activities.view')->get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');

    Route::middleware('permission:activities.update')->put('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');

    Route::middleware('permission:activities.delete')->delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::middleware('permission:activities.complete')->post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete');

});
