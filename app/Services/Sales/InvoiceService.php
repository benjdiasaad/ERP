<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $data['reference']  = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status']     = $data['status'] ?? 'draft';
            $data['amount_paid'] = $data['amount_paid'] ?? 0;

            $invoice = Invoice::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $invoice->lines()->create($this->calculateLineAmounts($lineData));
                }
            }

            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines', 'customer']);
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft invoices can be updated. Current status: {$invoice->status}.",
            ]);
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $data['updated_by'] = auth()->id();
            $invoice->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $invoice->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $calculated = $this->calculateLineAmounts($lineData);

                    if (!empty($lineData['id'])) {
                        $invoice->lines()->where('id', $lineData['id'])->update($calculated);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine = $invoice->lines()->create($calculated);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $invoice->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines', 'customer']);
        });
    }

    public function delete(Invoice $invoice): bool
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft invoices can be deleted. Current status: {$invoice->status}.",
            ]);
        }

        return (bool) $invoice->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft invoices can be sent. Current status: {$invoice->status}.",
            ]);
        }

        $invoice->update([
            'status'     => 'sent',
            'updated_by' => auth()->id(),
        ]);

        return $invoice->fresh(['lines', 'customer']);
    }

    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Invoice is already cancelled.',
            ]);
        }

        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Paid invoices cannot be cancelled.',
            ]);
        }

        $invoice->update([
            'status'     => 'cancelled',
            'notes'      => $reason ? trim(($invoice->notes ?? '') . "\nCancellation reason: {$reason}") : $invoice->notes,
            'updated_by' => auth()->id(),
        ]);

        return $invoice->fresh(['lines', 'customer']);
    }

    // ─── Payments ─────────────────────────────────────────────────────────────

    public function recordPayment(Invoice $invoice, float $amount, array $meta = []): Invoice
    {
        if (!in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot record payment for invoice with status '{$invoice->status}'.",
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $amountDue = (float) $invoice->amount_due;

        if ($amount > $amountDue + 0.001) {
            throw ValidationException::withMessages([
                'amount' => "Payment amount ({$amount}) exceeds amount due ({$amountDue}).",
            ]);
        }

        return DB::transaction(function () use ($invoice, $amount, $amountDue): Invoice {
            $newAmountPaid = round((float) $invoice->amount_paid + $amount, 2);
            $newAmountDue  = round($amountDue - $amount, 2);

            $newStatus = $newAmountDue <= 0 ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => max(0, $newAmountDue),
                'status'      => $newStatus,
                'updated_by'  => auth()->id(),
            ]);

            return $invoice->fresh(['lines', 'customer']);
        });
    }

    // ─── Credit Notes ─────────────────────────────────────────────────────────

    public function createCreditNote(Invoice $invoice, array $data): CreditNote
    {
        if (!in_array($invoice->status, ['sent', 'partial', 'paid', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot create credit note for invoice with status '{$invoice->status}'.",
            ]);
        }

        return DB::transaction(function () use ($invoice, $data): CreditNote {
            $creditNote = CreditNote::create([
                'company_id'  => $invoice->company_id,
                'invoice_id'  => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'reference'   => $this->generateCreditNoteReference(),
                'status'      => 'draft',
                'reason'      => $data['reason'] ?? null,
                'note_date'   => $data['note_date'] ?? now()->toDateString(),
                'currency_id' => $invoice->currency_id,
                'subtotal_ht'    => 0,
                'total_discount' => 0,
                'total_tax'      => 0,
                'total_ttc'      => 0,
                'created_by'  => auth()->id(),
            ]);

            $lines = $data['lines'] ?? $invoice->lines->toArray();

            foreach ($lines as $index => $lineData) {
                $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                $creditNote->lines()->create($this->calculateLineAmounts($lineData));
            }

            // Recalculate totals on the credit note
            $creditNote->loadMissing('lines');
            $subtotalHt    = $creditNote->lines->sum('subtotal_ht');
            $totalDiscount = $creditNote->lines->sum('discount_amount');
            $totalTax      = $creditNote->lines->sum('tax_amount');
            $totalTtc      = $subtotalHt - $totalDiscount + $totalTax;

            $creditNote->update([
                'subtotal_ht'    => round((float) $subtotalHt, 2),
                'total_discount' => round((float) $totalDiscount, 2),
                'total_tax'      => round((float) $totalTax, 2),
                'total_ttc'      => round((float) $totalTtc, 2),
            ]);

            return $creditNote->fresh(['lines']);
        });
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    public function calculateAmountDue(Invoice $invoice): float
    {
        return round(max(0, (float) $invoice->total_ttc - (float) $invoice->amount_paid), 2);
    }

    public function checkOverdue(Invoice $invoice): bool
    {
        if (!$invoice->isOverdue()) {
            return false;
        }

        if ($invoice->status !== 'overdue') {
            $invoice->update([
                'status'     => 'overdue',
                'updated_by' => auth()->id(),
            ]);
        }

        return true;
    }

    /**
     * Mark all sent/partial invoices past their due date as overdue.
     * Intended to be called from a scheduled command.
     */
    public function markOverdueInvoices(): int
    {
        return Invoice::overdue()
            ->where('status', '!=', 'overdue')
            ->update([
                'status'     => 'overdue',
                'updated_by' => auth()->id(),
            ]);
    }

    public function generatePdf(Invoice $invoice): string
    {
        // Returns the rendered HTML for PDF generation.
        // Actual PDF rendering (e.g. via Browsershot/DomPDF) is handled at the controller layer.
        $invoice->loadMissing(['lines.product', 'lines.tax', 'customer', 'currency', 'paymentTerm']);

        return view('pdf.invoices.invoice', compact('invoice'))->render();
    }

    public function calculateTotals(Invoice $invoice): void
    {
        $invoice->loadMissing('lines');

        $subtotalHt    = 0.0;
        $totalDiscount = 0.0;
        $totalTax      = 0.0;

        foreach ($invoice->lines as $line) {
            $subtotalHt    += (float) $line->subtotal_ht;
            $totalDiscount += (float) $line->discount_amount;
            $totalTax      += (float) $line->tax_amount;
        }

        $totalTtc  = $subtotalHt - $totalDiscount + $totalTax;
        $amountDue = round(max(0, $totalTtc - (float) $invoice->amount_paid), 2);

        $invoice->update([
            'subtotal_ht'    => round($subtotalHt, 2),
            'total_discount' => round($totalDiscount, 2),
            'total_tax'      => round($totalTax, 2),
            'total_ttc'      => round($totalTtc, 2),
            'amount_due'     => $amountDue,
        ]);
    }

    public function calculateLineAmounts(array $lineData): array
    {
        $quantity      = (float) ($lineData['quantity'] ?? 0);
        $unitPriceHt   = (float) ($lineData['unit_price_ht'] ?? 0);
        $discountType  = $lineData['discount_type'] ?? 'percentage';
        $discountValue = (float) ($lineData['discount_value'] ?? 0);
        $taxRate       = (float) ($lineData['tax_rate'] ?? 0);

        $subtotalHt = $quantity * $unitPriceHt;

        $discountAmount = $discountType === 'percentage'
            ? $subtotalHt * ($discountValue / 100)
            : $discountValue;

        $subtotalHtAfterDiscount = $subtotalHt - $discountAmount;
        $taxAmount = $subtotalHtAfterDiscount * ($taxRate / 100);
        $totalTtc  = $subtotalHtAfterDiscount + $taxAmount;

        return array_merge($lineData, [
            'subtotal_ht'                => round($subtotalHt, 2),
            'discount_amount'            => round($discountAmount, 2),
            'subtotal_ht_after_discount' => round($subtotalHtAfterDiscount, 2),
            'tax_amount'                 => round($taxAmount, 2),
            'total_ttc'                  => round($totalTtc, 2),
        ]);
    }

    // ─── Reference Generators ─────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = Invoice::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('FAC-%d-%05d', $year, $count);
    }

    private function generateCreditNoteReference(): string
    {
        $year  = now()->year;
        $count = CreditNote::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('AV-%d-%05d', $year, $count);
    }
}
