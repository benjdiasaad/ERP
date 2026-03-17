<?php

use App\Http\Controllers\Sales\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:sales_orders.view_any')->get('sales-orders', [SalesOrderController::class, 'index'])->name('sales-orders.index');
    
    Route::middleware('permission:sales_orders.create')->post('sales-orders', [SalesOrderController::class, 'store'])->name('sales-orders.store');
    
    Route::middleware('permission:sales_orders.view')->get('sales-orders/{salesOrder}', [SalesOrderController::class, 'show'])->name('sales-orders.show');
    
    Route::middleware('permission:sales_orders.update')->put('sales-orders/{salesOrder}', [SalesOrderController::class, 'update'])->name('sales-orders.update');
    
    Route::middleware('permission:sales_orders.delete')->delete('sales-orders/{salesOrder}', [SalesOrderController::class, 'destroy'])->name('sales-orders.destroy');
    
    Route::middleware('permission:sales_orders.confirm')->post('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm');
    
    Route::middleware('permission:sales_orders.cancel')->post('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel');
    
    Route::middleware('permission:sales_orders.generate_invoice')->post('sales-orders/{salesOrder}/generate-invoice', [SalesOrderController::class, 'generateInvoice'])->name('sales-orders.generate-invoice');
    
    Route::middleware('permission:sales_orders.generate_delivery_note')->post('sales-orders/{salesOrder}/generate-delivery-note', [SalesOrderController::class, 'generateDeliveryNote'])->name('sales-orders.generate-delivery-note');
    
    Route::middleware('permission:sales_orders.print')->get('sales-orders/{salesOrder}/pdf', [SalesOrderController::class, 'pdf'])->name('sales-orders.pdf');

});
