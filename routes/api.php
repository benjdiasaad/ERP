<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
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
});
