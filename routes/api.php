<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Personnel\AttendanceController;
use App\Http\Controllers\Personnel\ContractController;
use App\Http\Controllers\Personnel\DepartmentController;
use App\Http\Controllers\Personnel\LeaveController;
use App\Http\Controllers\Personnel\PersonnelController;
use App\Http\Controllers\Personnel\PositionController;
use App\Http\Controllers\Sales\CustomerController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Protected auth routes
Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
    Route::post('refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('change-password', [AuthController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    // Company CRUD
    Route::apiResource('companies', CompanyController::class);

    // Company user management
    Route::post('companies/{company}/users', [CompanyController::class, 'addUser']);
    Route::delete('companies/{company}/users/{user}', [CompanyController::class, 'removeUser']);

    // Company switch
    Route::post('companies/{company}/switch', [CompanyController::class, 'switch']);

    // Roles CRUD
    Route::middleware('permission:roles.view_any')->get('roles', [RoleController::class, 'index']);
    Route::middleware('permission:roles.create')->post('roles', [RoleController::class, 'store']);
    Route::middleware('permission:roles.view')->get('roles/{role}', [RoleController::class, 'show']);
    Route::middleware('permission:roles.update')->put('roles/{role}', [RoleController::class, 'update']);
    Route::middleware('permission:roles.delete')->delete('roles/{role}', [RoleController::class, 'destroy']);

    // Role permissions management
    Route::middleware('permission:roles.update')->group(function () {
        Route::post('roles/{role}/permissions', [RoleController::class, 'assignPermissions']);
        Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
        Route::delete('roles/{role}/permissions', [RoleController::class, 'revokePermissions']);
    });

    // Role user assignment
    Route::middleware('permission:roles.update')->group(function () {
        Route::post('roles/{role}/users', [RoleController::class, 'assignToUser']);
        Route::delete('roles/{role}/users/{user}', [RoleController::class, 'removeFromUser']);
    });

    // Permissions (read-only, grouped by module)
    Route::middleware('permission:permissions.view_any')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('permissions/grouped', [PermissionController::class, 'grouped']);
        Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    });

    // Personnel Module
    Route::middleware('permission:departments.view_any')->get('departments', [DepartmentController::class, 'index']);
    Route::middleware('permission:departments.create')->post('departments', [DepartmentController::class, 'store']);
    Route::middleware('permission:departments.view')->get('departments/{department}', [DepartmentController::class, 'show']);
    Route::middleware('permission:departments.update')->put('departments/{department}', [DepartmentController::class, 'update']);
    Route::middleware('permission:departments.delete')->delete('departments/{department}', [DepartmentController::class, 'destroy']);

    Route::middleware('permission:positions.view_any')->get('positions', [PositionController::class, 'index']);
    Route::middleware('permission:positions.create')->post('positions', [PositionController::class, 'store']);
    Route::middleware('permission:positions.view')->get('positions/{position}', [PositionController::class, 'show']);
    Route::middleware('permission:positions.update')->put('positions/{position}', [PositionController::class, 'update']);
    Route::middleware('permission:positions.delete')->delete('positions/{position}', [PositionController::class, 'destroy']);

    Route::middleware('permission:personnels.view_any')->get('personnels', [PersonnelController::class, 'index']);
    Route::middleware('permission:personnels.create')->post('personnels', [PersonnelController::class, 'store']);
    Route::middleware('permission:personnels.view')->get('personnels/{personnel}', [PersonnelController::class, 'show']);
    Route::middleware('permission:personnels.update')->put('personnels/{personnel}', [PersonnelController::class, 'update']);
    Route::middleware('permission:personnels.delete')->delete('personnels/{personnel}', [PersonnelController::class, 'destroy']);

    Route::middleware('permission:contracts.view_any')->get('contracts', [ContractController::class, 'index']);
    Route::middleware('permission:contracts.create')->post('contracts', [ContractController::class, 'store']);
    Route::middleware('permission:contracts.view')->get('contracts/{contract}', [ContractController::class, 'show']);
    Route::middleware('permission:contracts.update')->put('contracts/{contract}', [ContractController::class, 'update']);
    Route::middleware('permission:contracts.delete')->delete('contracts/{contract}', [ContractController::class, 'destroy']);

    Route::middleware('permission:leaves.view_any')->get('leaves', [LeaveController::class, 'index']);
    Route::middleware('permission:leaves.create')->post('leaves', [LeaveController::class, 'store']);
    Route::middleware('permission:leaves.view')->get('leaves/{leave}', [LeaveController::class, 'show']);
    Route::middleware('permission:leaves.update')->put('leaves/{leave}', [LeaveController::class, 'update']);
    Route::middleware('permission:leaves.delete')->delete('leaves/{leave}', [LeaveController::class, 'destroy']);
    Route::middleware('permission:leaves.update')->post('leaves/{leave}/approve', [LeaveController::class, 'approve']);
    Route::middleware('permission:leaves.update')->post('leaves/{leave}/reject', [LeaveController::class, 'reject']);

    Route::middleware('permission:attendances.view_any')->get('attendances', [AttendanceController::class, 'index']);
    Route::middleware('permission:attendances.create')->post('attendances', [AttendanceController::class, 'store']);
    Route::middleware('permission:attendances.view')->get('attendances/{attendance}', [AttendanceController::class, 'show']);
    Route::middleware('permission:attendances.update')->put('attendances/{attendance}', [AttendanceController::class, 'update']);
    Route::middleware('permission:attendances.delete')->delete('attendances/{attendance}', [AttendanceController::class, 'destroy']);

    // Sales — Customers
    Route::middleware('permission:customers.view_any')->get('customers', [CustomerController::class, 'index']);
    Route::middleware('permission:customers.create')->post('customers', [CustomerController::class, 'store']);
    Route::middleware('permission:customers.view_any')->get('customers/search', [CustomerController::class, 'search']);
    Route::middleware('permission:customers.view')->get('customers/{customer}', [CustomerController::class, 'show']);
    Route::middleware('permission:customers.update')->put('customers/{customer}', [CustomerController::class, 'update']);
    Route::middleware('permission:customers.delete')->delete('customers/{customer}', [CustomerController::class, 'destroy']);
    Route::middleware('permission:customers.view')->get('customers/{customer}/credit-info', [CustomerController::class, 'creditInfo']);
});
