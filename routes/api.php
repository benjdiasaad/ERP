<?php

declare(strict_types=1);

use App\Http\Controllers\Company\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    // Company CRUD
    Route::apiResource('companies', CompanyController::class);

    // Company user management
    Route::post('companies/{company}/users', [CompanyController::class, 'addUser']);
    Route::delete('companies/{company}/users/{user}', [CompanyController::class, 'removeUser']);

    // Company switch
    Route::post('companies/{company}/switch', [CompanyController::class, 'switch']);
});
