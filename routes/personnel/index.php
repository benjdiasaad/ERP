<?php

use App\Http\Controllers\Personnel\{AttendanceController, ContractController, DepartmentController, LeaveController, PersonnelController, PositionController};
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::middleware('permission:personnels.view_any')->get('personnels', [PersonnelController::class, 'index'])->name('personnels.index');
    Route::middleware('permission:personnels.create')->post('personnels', [PersonnelController::class, 'store'])->name('personnels.store');
    Route::middleware('permission:personnels.view')->get('personnels/{personnel}', [PersonnelController::class, 'show'])->name('personnels.show');
    Route::middleware('permission:personnels.update')->put('personnels/{personnel}', [PersonnelController::class, 'update'])->name('personnels.update');
    Route::middleware('permission:personnels.delete')->delete('personnels/{personnel}', [PersonnelController::class, 'destroy'])->name('personnels.destroy');

    Route::middleware('permission:departments.view_any')->get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::middleware('permission:departments.create')->post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::middleware('permission:departments.view')->get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::middleware('permission:departments.update')->put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::middleware('permission:departments.delete')->delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::middleware('permission:positions.view_any')->get('positions', [PositionController::class, 'index'])->name('positions.index');
    Route::middleware('permission:positions.create')->post('positions', [PositionController::class, 'store'])->name('positions.store');
    Route::middleware('permission:positions.view')->get('positions/{position}', [PositionController::class, 'show'])->name('positions.show');
    Route::middleware('permission:positions.update')->put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
    Route::middleware('permission:positions.delete')->delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

    Route::middleware('permission:contracts.view_any')->get('contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::middleware('permission:contracts.create')->post('contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::middleware('permission:contracts.view')->get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::middleware('permission:contracts.update')->put('contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::middleware('permission:contracts.delete')->delete('contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');

    Route::middleware('permission:leaves.view_any')->get('leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::middleware('permission:leaves.create')->post('leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::middleware('permission:leaves.view')->get('leaves/{leave}', [LeaveController::class, 'show'])->name('leaves.show');
    Route::middleware('permission:leaves.update')->put('leaves/{leave}', [LeaveController::class, 'update'])->name('leaves.update');
    Route::middleware('permission:leaves.delete')->delete('leaves/{leave}', [LeaveController::class, 'destroy'])->name('leaves.destroy');

    Route::middleware('permission:attendances.view_any')->get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::middleware('permission:attendances.create')->post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
    Route::middleware('permission:attendances.view')->get('attendances/{attendance}', [AttendanceController::class, 'show'])->name('attendances.show');
    Route::middleware('permission:attendances.update')->put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::middleware('permission:attendances.delete')->delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');

    Route::middleware('permission:leaves.approve')->post('leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::middleware('permission:leaves.reject')->post('leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
});
