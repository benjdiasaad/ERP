<?php

use App\Http\Controllers\Purchasing\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:purchase_orders.view_any')->get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');

    Route::middleware('permission:purchase_orders.create')->post('purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');

    Route::middleware('permission:purchase_orders.view')->get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');

    Route::middleware('permission:purchase_orders.update')->put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');

    Route::middleware('permission:purchase_orders.delete')->delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy');

    Route::middleware('permission:purchase_orders.send')->post('purchase-orders/{purchaseOrder}/send', [PurchaseOrderController::class, 'send'])->name('purchase-orders.send');

    Route::middleware('permission:purchase_orders.confirm')->post('purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm');

    Route::middleware('permission:purchase_orders.cancel')->post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

    Route::middleware('permission:purchase_orders.generate_reception')->post('purchase-orders/{purchaseOrder}/generate-reception', [PurchaseOrderController::class, 'generateReception'])->name('purchase-orders.generate-reception');

    Route::middleware('permission:purchase_orders.generate_invoice')->post('purchase-orders/{purchaseOrder}/generate-invoice', [PurchaseOrderController::class, 'generateInvoice'])->name('purchase-orders.generate-invoice');

});
