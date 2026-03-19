<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\PurchaseInvoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data): PurchaseInvoice {
            $data['reference']   = $data['reference'] ?? $this->generateReference();
            $data['created_by']  = auth()->id();
            $data['status']      = $data['status'] ?? 'draft';
            $data['amount_paid'] = $data['amount_paid'] ?? 0;

            $invoice = PurchaseInvoice::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $invoice->lines()->create($this->calculateLineAmounts($lineData));
                }
            }

            $this->calculateTotals($invoice);

            return $invoice->fresh(['lines', 'supplier']);
        });
    }

    public function update(PurchaseInvoice $invoice, array $data): PurchaseInvoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase invoices can be updated. Current status: {$invoice->status}.",
            ]);
        }

        return DB::transaction(function () use ($invoice, $data): PurchaseInvoice {
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

            return $invoice->fresh(['lines', 'supplier']);
        });
    }

    public function delete(PurchaseInvoice $invoice): bool
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase invoices can be deleted. Current status: {$invoice->status}.",
            ]);
        }

        return (bool) $invoice->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function send(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase invoices can be sent. Current status: {$invoice->status}.",
            ]);
        }

        $invoice->update([
            'status'     => 'sent',
            'updated_by' => auth()->id(),
        ]);

        return $invoice->fresh(['lines', 'supplier']);
    }

    public function cancel(PurchaseInvoice $invoice, ?string $reason = null): PurchaseInvoice
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Purchase invoice is already cancelled.',
            ]);
        }

        if ($invoice->status === 'paid') {
            throw ValidationException::withMessages([
                'status' => 'Paid purchase invoices cannot be cancelled.',
            ]);
        }

        $invoice->update([
            'status'     => 'cancelled',
            'notes'      => $reason ? trim(($invoice->notes ?? '') . "\nCancellation reason: {$reason}") : $invoice->notes,
            'updated_by' => auth()->id(),
        ]);

        return $invoice->fresh(['lines', 'supplier']);
    }

    // ─── Payments ─────────────────────────────────────────────────────────────

    public function recordPayment(PurchaseInvoice $invoice, float $amount, array $meta = []): PurchaseInvoice
    {
        if (!in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot record payment for purchase invoice with status '{$invoice->status}'.",
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

        return DB::transaction(function () use ($invoice, $amount, $amountDue): PurchaseInvoice {
            $newAmountPaid = round((float) $invoice->amount_paid + $amount, 2);
            $newAmountDue  = round($amountDue - $amount, 2);
            $newStatus     = $newAmountDue <= 0 ? 'paid' : 'partial';

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'amount_due'  => max(0, $newAmountDue),
                'status'      => $newStatus,
                'updated_by'  => auth()->id(),
            ]);

            return $invoice->fresh(['lines', 'supplier']);
        });
    }

    public function markPaid(PurchaseInvoice $invoice): PurchaseInvoice
    {
        if (!in_array($invoice->status, ['sent', 'partial', 'overdue'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot mark purchase invoice as paid with status '{$invoice->status}'.",
            ]);
        }

        $invoice->update([
            'amount_paid' => $invoice->total_ttc,
            'amount_due'  => 0,
            'status'      => 'paid',
            'updated_by'  => auth()->id(),
        ]);

        return $invoice->fresh(['lines', 'supplier']);
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    public function calculateTotals(PurchaseInvoice $invoice): void
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

    // ─── Reference Generator ──────────────────────────────────────────────────

    public function generateReference(): string
    {
        $year  = now()->year;
        $count = PurchaseInvoice::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('FAF-%d-%05d', $year, $count);
    }
}
