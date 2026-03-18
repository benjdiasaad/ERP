<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\ReceptionNote;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data): PurchaseOrder {
            $data['reference'] = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status'] = $data['status'] ?? 'draft';

            $order = PurchaseOrder::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $line = $this->calculateLineAmounts($lineData);
                    $order->lines()->create($line);
                }
            }

            $this->calculateTotals($order);

            return $order->fresh(['lines', 'supplier']);
        });
    }

    public function update(PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase orders can be updated. Current status: {$order->status}.",
            ]);
        }

        return DB::transaction(function () use ($order, $data): PurchaseOrder {
            $order->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $order->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $calculated = $this->calculateLineAmounts($lineData);

                    if (!empty($lineData['id'])) {
                        $order->lines()->where('id', $lineData['id'])->update($calculated);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine = $order->lines()->create($calculated);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $order->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            $this->calculateTotals($order);

            return $order->fresh(['lines', 'supplier']);
        });
    }

    public function delete(PurchaseOrder $order): void
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase orders can be deleted. Current status: {$order->status}.",
            ]);
        }

        $order->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function send(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase orders can be sent. Current status: {$order->status}.",
            ]);
        }

        $order->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $order->fresh(['lines', 'supplier']);
    }

    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => "Only sent purchase orders can be confirmed. Current status: {$order->status}.",
            ]);
        }

        $order->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return $order->fresh(['lines', 'supplier']);
    }

    public function cancel(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        if (in_array($order->status, ['received', 'invoiced', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "Purchase orders with status '{$order->status}' cannot be cancelled.",
            ]);
        }

        $order->update([
            'status'              => 'cancelled',
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $order->fresh(['lines', 'supplier']);
    }

    // ─── Document Generation (stubs) ─────────────────────────────────────────

    public function generateReceptionNote(PurchaseOrder $order): ReceptionNote
    {
        if (!in_array($order->status, ['confirmed', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot generate reception note for order with status '{$order->status}'.",
            ]);
        }

        return DB::transaction(function () use ($order): ReceptionNote {
            $order->loadMissing('lines');

            $year  = now()->year;
            $count = ReceptionNote::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;
            $reference = sprintf('BR-%d-%05d', $year, $count);

            $note = ReceptionNote::create([
                'company_id'        => $order->company_id,
                'reference'         => $reference,
                'purchase_order_id' => $order->id,
                'supplier_id'       => $order->supplier_id,
                'status'            => 'draft',
                'reception_date'    => now()->toDateString(),
                'created_by'        => auth()->id(),
            ]);

            foreach ($order->lines as $line) {
                $remaining = (float) $line->remainingToReceive();
                if ($remaining <= 0) {
                    continue;
                }

                $note->lines()->create([
                    'company_id'             => $line->company_id,
                    'purchase_order_line_id' => $line->id,
                    'product_id'             => $line->product_id,
                    'description'            => $line->description,
                    'ordered_quantity'        => $line->quantity,
                    'received_quantity'       => $remaining,
                    'rejected_quantity'       => 0,
                    'unit'                   => $line->unit,
                    'sort_order'             => $line->sort_order,
                ]);
            }

            return $note->fresh(['lines']);
        });
    }

    public function generatePurchaseInvoice(PurchaseOrder $order): array
    {
        if (!in_array($order->status, ['confirmed', 'in_progress', 'received'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot generate purchase invoice for order with status '{$order->status}'.",
            ]);
        }

        // TODO: Implement once PurchaseInvoice model is available (Task 15).
        return ['message' => 'Purchase invoice generation pending Task 15 implementation.', 'order_id' => $order->id];
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    public function calculateTotals(PurchaseOrder $order): void
    {
        $order->loadMissing('lines');

        $subtotalHt    = 0.0;
        $totalDiscount = 0.0;
        $totalTax      = 0.0;

        foreach ($order->lines as $line) {
            $subtotalHt    += (float) $line->subtotal_ht;
            $totalDiscount += (float) $line->discount_amount;
            $totalTax      += (float) $line->tax_amount;
        }

        $totalTtc = $subtotalHt - $totalDiscount + $totalTax;

        $order->update([
            'subtotal_ht'    => round($subtotalHt, 2),
            'total_discount' => round($totalDiscount, 2),
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
            'subtotal_ht'                => round($subtotalHt, 2),
            'discount_amount'            => round($discountAmount, 2),
            'subtotal_ht_after_discount' => round($subtotalHtAfterDiscount, 2),
            'tax_amount'                 => round($taxAmount, 2),
            'total_ttc'                  => round($totalTtc, 2),
        ]);
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = PurchaseOrder::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('PO-%d-%05d', $year, $count);
    }
}
