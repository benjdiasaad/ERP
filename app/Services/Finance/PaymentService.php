<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Payment;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Sales\Invoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new payment (draft status).
     */
    public function create(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $data['reference'] = $data['reference'] ?? $this->generateReference();
            $data['status']    = $data['status'] ?? 'pending';

            $payment = Payment::create($data);

            return $payment->fresh(['payable', 'paymentMethod', 'bankAccount', 'confirmedBy']);
        });
    }

    /**
     * Update a payment (only allowed in pending status).
     */
    public function update(Payment $payment, array $data): Payment
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Only pending payments can be updated. Current status: {$payment->status}.",
            ]);
        }

        $payment->update($data);

        return $payment->fresh(['payable', 'paymentMethod', 'bankAccount', 'confirmedBy']);
    }

    /**
     * Soft-delete a payment (only allowed in pending status).
     */
    public function delete(Payment $payment): bool
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Only pending payments can be deleted. Current status: {$payment->status}.",
            ]);
        }

        return (bool) $payment->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Confirm a payment (transition from pending → confirmed).
     * Creates a journal entry, updates invoice/purchase invoice balance, and updates bank account balance.
     */
    public function confirm(Payment $payment, ?string $notes = null): Payment
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Only pending payments can be confirmed. Current status: {$payment->status}.",
            ]);
        }

        if (!$payment->payable) {
            throw ValidationException::withMessages([
                'payable' => 'Payment must be linked to an invoice or purchase invoice.',
            ]);
        }

        if (!$payment->bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => 'Payment must be linked to a bank account.',
            ]);
        }

        return DB::transaction(function () use ($payment, $notes): Payment {
            // Create journal entry for the payment
            $journalEntry = $this->createPaymentJournalEntry($payment);

            // Update payable (Invoice or PurchaseInvoice) balance
            $this->updatePayableBalance($payment);

            // Update bank account balance
            $this->updateBankAccountBalance($payment);

            // Mark payment as confirmed
            $payment->update([
                'status'       => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
                'notes'        => $notes ? trim(($payment->notes ?? '') . "\n{$notes}") : $payment->notes,
            ]);

            return $payment->fresh(['payable', 'paymentMethod', 'bankAccount', 'confirmedBy']);
        });
    }

    /**
     * Cancel a payment (only allowed in pending status).
     */
    public function cancel(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Only pending payments can be cancelled. Current status: {$payment->status}.",
            ]);
        }

        $payment->update([
            'status' => 'cancelled',
            'notes'  => $reason ? trim(($payment->notes ?? '') . "\nCancellation reason: {$reason}") : $payment->notes,
        ]);

        return $payment->fresh(['payable', 'paymentMethod', 'bankAccount', 'confirmedBy']);
    }

    // ─── Journal Entry Creation ────────────────────────────────────────────────

    /**
     * Create a journal entry for the payment.
     * For incoming payments (customer): Debit bank account, Credit accounts receivable
     * For outgoing payments (supplier): Debit accounts payable, Credit bank account
     */
    private function createPaymentJournalEntry(Payment $payment): JournalEntry
    {
        $bankAccountId = $payment->bankAccount->id;
        $amount        = (float) $payment->amount;

        // Determine the counterparty account based on payable type
        $counterpartyAccountId = $this->getCounterpartyAccountId($payment);

        if ($payment->direction === 'incoming') {
            // Customer payment: Debit bank, Credit AR
            $lines = [
                [
                    'chart_of_account_id' => $bankAccountId,
                    'debit'               => $amount,
                    'credit'              => 0,
                    'description'         => "Payment received from {$payment->payable->customer->name ?? 'Customer'} - {$payment->reference}",
                    'sort_order'          => 0,
                ],
                [
                    'chart_of_account_id' => $counterpartyAccountId,
                    'debit'               => 0,
                    'credit'              => $amount,
                    'description'         => "Accounts receivable - {$payment->payable->reference ?? 'Invoice'}",
                    'sort_order'          => 1,
                ],
            ];
        } else {
            // Supplier payment: Debit AP, Credit bank
            $lines = [
                [
                    'chart_of_account_id' => $counterpartyAccountId,
                    'debit'               => $amount,
                    'credit'              => 0,
                    'description'         => "Accounts payable - {$payment->payable->reference ?? 'Invoice'}",
                    'sort_order'          => 0,
                ],
                [
                    'chart_of_account_id' => $bankAccountId,
                    'debit'               => 0,
                    'credit'              => $amount,
                    'description'         => "Payment to {$payment->payable->supplier->name ?? 'Supplier'} - {$payment->reference}",
                    'sort_order'          => 1,
                ],
            ];
        }

        return $this->journalEntryService->create([
            'company_id'  => $payment->company_id,
            'reference'   => "JE-{$payment->reference}",
            'description' => "Payment: {$payment->reference}",
            'entry_date'  => $payment->payment_date,
            'status'      => 'draft',
            'lines'       => $lines,
        ]);
    }

    /**
     * Get the counterparty account ID based on the payable type.
     */
    private function getCounterpartyAccountId(Payment $payment): int
    {
        // This is a simplified implementation. In production, you'd want to:
        // 1. Look up the appropriate account from the payable's customer/supplier
        // 2. Or use a default AR/AP account from settings
        // For now, we'll use a placeholder that should be configured per company

        if ($payment->direction === 'incoming') {
            // Accounts Receivable account - should be configured in settings
            // Placeholder: return a default AR account ID
            return 1; // This should be fetched from company settings
        } else {
            // Accounts Payable account - should be configured in settings
            // Placeholder: return a default AP account ID
            return 2; // This should be fetched from company settings
        }
    }

    // ─── Balance Updates ──────────────────────────────────────────────────────

    /**
     * Update the payable (Invoice or PurchaseInvoice) balance.
     */
    private function updatePayableBalance(Payment $payment): void
    {
        $payable = $payment->payable;
        $amount  = (float) $payment->amount;

        if ($payable instanceof Invoice) {
            $newAmountPaid = round((float) $payable->amount_paid + $amount, 2);
            $newAmountDue  = round(max(0, (float) $payable->total_ttc - $newAmountPaid), 2);
            $newStatus     = $newAmountDue <= 0 ? 'paid' : 'partial';

            $payable->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => $newAmountDue,
                'status'      => $newStatus,
                'updated_by'  => auth()->id(),
            ]);
        } elseif ($payable instanceof PurchaseInvoice) {
            $newAmountPaid = round((float) $payable->amount_paid + $amount, 2);
            $newAmountDue  = round(max(0, (float) $payable->total_ttc - $newAmountPaid), 2);
            $newStatus     = $newAmountDue <= 0 ? 'paid' : 'partial';

            $payable->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => $newAmountDue,
                'status'      => $newStatus,
                'updated_by'  => auth()->id(),
            ]);
        }
    }

    /**
     * Update the bank account balance.
     */
    private function updateBankAccountBalance(Payment $payment): void
    {
        $bankAccount = $payment->bankAccount;
        $amount      = (float) $payment->amount;

        if ($payment->direction === 'incoming') {
            // Incoming payment: increase bank balance
            $newBalance = round((float) $bankAccount->balance + $amount, 2);
        } else {
            // Outgoing payment: decrease bank balance
            $newBalance = round((float) $bankAccount->balance - $amount, 2);
        }

        $bankAccount->update(['balance' => $newBalance]);
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    /**
     * Generate a unique payment reference.
     */
    private function generateReference(): string
    {
        $year  = now()->year;
        $count = Payment::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('PAY-%d-%05d', $year, $count);
    }
}
