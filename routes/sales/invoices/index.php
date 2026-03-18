<?php

use App\Http\Controllers\Sales\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'company'])->group(function () {

    Route::middleware('permission:invoices.view_any')->get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');

    Route::middleware('permission:invoices.create')->post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');

    Route::middleware('permission:invoices.view_any')->get('invoices/overdue', [InvoiceController::class, 'overdue'])->name('invoices.overdue');

    Route::middleware('permission:invoices.view')->get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');

    Route::middleware('permission:invoices.update')->put('invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');

    Route::middleware('permission:invoices.delete')->delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::middleware('permission:invoices.send')->post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');

    Route::middleware('permission:invoices.cancel')->post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    Route::middleware('permission:invoices.record_payment')->post('invoices/{invoice}/record-payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');

    Route::middleware('permission:invoices.create_credit_note')->post('invoices/{invoice}/credit-note', [InvoiceController::class, 'createCreditNote'])->name('invoices.credit-note');

    Route::middleware('permission:invoices.print')->get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

});
