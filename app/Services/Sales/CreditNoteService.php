<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\CreditNote;
use App\Models\Sales\Invoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): CreditNote
    {
        return DB::transaction(function () use ($data): CreditNote {
            $data['reference']  = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status']     = $data['status'] ?? 'draft';
            $data['date']       = $data['date'] ?? now()->toDateString();

            $creditNote = CreditNote::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $creditNote->lines()->create($this->calculateLineAmounts($lineData));
                }
            }

            $this->calculateTotals($creditNote);

            return $creditNote->fresh(['lines', 'customer', 'invoice']);
        });
    }

    public function update(CreditNote $creditNote, array $data): CreditNote
    {
        if ($creditNote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft credit notes can be updated. Current status: {$creditNote->status}.",
            ]);
        }

        return DB::transaction(function () use ($creditNote, $data): CreditNote {
            $creditNote->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $creditNote->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $calculated = $this->calculateLineAmounts($lineData);

                    if (!empty($lineData['id'])) {
                        $creditNote->lines()->where('id', $lineData['id'])->update($calculated);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine = $creditNote->lines()->create($calculated);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $creditNote->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            $this->calculateTotals($creditNote);

            return $creditNote->fresh(['lines', 'customer', 'invoice']);
        });
    }

    public function delete(CreditNote $creditNote): void
    {
        if ($creditNote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft credit notes can be deleted. Current status: {$creditNote->status}.",
            ]);
        }

        $creditNote->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function confirm(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft credit notes can be confirmed. Current status: {$creditNote->status}.",
            ]);
        }

        $creditNote->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);

        return $creditNote->fresh(['lines', 'customer', 'invoice']);
    }

    public function applyToInvoice(CreditNote $creditNote, Invoice $invoice): CreditNote
    {
        if ($creditNote->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'status' => "Only confirmed credit notes can be applied. Current status: {$creditNote->status}.",
            ]);
        }

        if (!in_array($invoice->status, ['sent', 'partial', 'overdue', 'paid'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot apply credit note to invoice with status '{$invoice->status}'.",
            ]);
        }

        return DB::transaction(function () use ($creditNote, $invoice): CreditNote {
            $creditAmount = (float) $creditNote->total_ttc;
            $amountDue    = (float) $invoice->amount_due;

            $newAmountDue  = round(max(0, $amountDue - $creditAmount), 2);
            $newAmountPaid = round((float) $invoice->amount_paid + min($creditAmount, $amountDue), 2);

            $newInvoiceStatus = $newAmountDue <= 0 ? 'paid' : 'partial';

            $invoice->update([
                'amount_due'  => $newAmountDue,
                'amount_paid' => $newAmountPaid,
                'status'      => $newInvoiceStatus,
            ]);

            $creditNote->update([
                'status'     => 'applied',
                'invoice_id' => $invoice->id,
                'applied_at' => now(),
            ]);

            return $creditNote->fresh(['lines', 'customer', 'invoice']);
        });
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    public function calculateTotals(CreditNote $creditNote): void
    {
        $creditNote->loadMissing('lines');

        $subtotalHt = 0.0;
        $totalTax   = 0.0;
        $totalTtc   = 0.0;

        foreach ($creditNote->lines as $line) {
            $subtotalHt += (float) $line->subtotal_ht;
            $totalTax   += (float) $line->tax_amount;
            $totalTtc   += (float) $line->total_ttc;
        }

        $creditNote->update([
            'subtotal_ht'    => round($subtotalHt, 2),
            'total_discount' => 0,
            'total_tax'      => round($totalTax, 2),
            'total_ttc'      => round($totalTtc, 2),
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
            'subtotal_ht'    => round($subtotalHt, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_amount'     => round($taxAmount, 2),
            'total_ttc'      => round($totalTtc, 2),
        ]);
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = CreditNote::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('AV-%d-%05d', $year, $count);
    }
}
