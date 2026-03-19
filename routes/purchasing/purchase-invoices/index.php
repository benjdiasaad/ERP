<?php

use App\Http\Controllers\Purchasing\PurchaseInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:purchase_invoices.view_any')->get('purchase-invoices', [PurchaseInvoiceController::class, 'index'])->name('purchase-invoices.index');

    Route::middleware('permission:purchase_invoices.create')->post('purchase-invoices', [PurchaseInvoiceController::class, 'store'])->name('purchase-invoices.store');

    Route::middleware('permission:purchase_invoices.view')->get('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->name('purchase-invoices.show');

    Route::middleware('permission:purchase_invoices.update')->put('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('purchase-invoices.update');

    Route::middleware('permission:purchase_invoices.delete')->delete('purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('purchase-invoices.destroy');

    Route::middleware('permission:purchase_invoices.send')->post('purchase-invoices/{purchaseInvoice}/send', [PurchaseInvoiceController::class, 'send'])->name('purchase-invoices.send');

    Route::middleware('permission:purchase_invoices.cancel')->post('purchase-invoices/{purchaseInvoice}/cancel', [PurchaseInvoiceController::class, 'cancel'])->name('purchase-invoices.cancel');

    Route::middleware('permission:purchase_invoices.record_payment')->post('purchase-invoices/{purchaseInvoice}/record-payment', [PurchaseInvoiceController::class, 'recordPayment'])->name('purchase-invoices.record-payment');

    Route::middleware('permission:purchase_invoices.record_payment')->post('purchase-invoices/{purchaseInvoice}/mark-paid', [PurchaseInvoiceController::class, 'markPaid'])->name('purchase-invoices.mark-paid');

    Route::middleware('permission:purchase_invoices.view')->get('purchase-invoices/{purchaseInvoice}/pdf', [PurchaseInvoiceController::class, 'pdf'])->name('purchase-invoices.pdf');

});
