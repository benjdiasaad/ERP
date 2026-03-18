<?php

use App\Http\Controllers\Purchasing\PurchaseRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:purchase_requests.view_any')->get('purchase-requests', [PurchaseRequestController::class, 'index'])->name('purchase-requests.index');

    Route::middleware('permission:purchase_requests.create')->post('purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase-requests.store');

    Route::middleware('permission:purchase_requests.view')->get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('purchase-requests.show');

    Route::middleware('permission:purchase_requests.update')->put('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase-requests.update');

    Route::middleware('permission:purchase_requests.delete')->delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('purchase-requests.destroy');

    Route::middleware('permission:purchase_requests.submit')->post('purchase-requests/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('purchase-requests.submit');

    Route::middleware('permission:purchase_requests.approve')->post('purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('purchase-requests.approve');

    Route::middleware('permission:purchase_requests.reject')->post('purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('purchase-requests.reject');

    Route::middleware('permission:purchase_requests.cancel')->post('purchase-requests/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->name('purchase-requests.cancel');

    Route::middleware('permission:purchase_requests.convert')->post('purchase-requests/{purchaseRequest}/convert-to-order', [PurchaseRequestController::class, 'convertToOrder'])->name('purchase-requests.convert-to-order');

});
