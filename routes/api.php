<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PermissionController;
use App\Http\Controllers\Auth\RoleController;
use App\Http\Controllers\Company\CompanyController;
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
});
