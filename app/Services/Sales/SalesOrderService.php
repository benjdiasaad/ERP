<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\DeliveryNote;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new sales order with lines and auto-generated reference.
     */
    public function create(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data): SalesOrder {
            $data['reference'] = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status'] = $data['status'] ?? 'draft';

            $order = SalesOrder::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $line = $this->calculateLineAmounts($lineData);
                    $order->lines()->create($line);
                }
            }

            $this->calculateTotals($order);

            return $order->fresh(['lines', 'customer']);
        });
    }

    /**
     * Update a sales order and sync its lines (only allowed in draft status).
     */
    public function update(SalesOrder $order, array $data): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft orders can be updated. Current status: {$order->status}.",
            ]);
        }

        return DB::transaction(function () use ($order, $data): SalesOrder {
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

            return $order->fresh(['lines', 'customer']);
        });
    }

    /**
     * Soft-delete a sales order (only allowed in draft status).
     */
    public function delete(SalesOrder $order): bool
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft orders can be deleted. Current status: {$order->status}.",
            ]);
        }

        return (bool) $order->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Transition order from draft → confirmed.
     */
    public function confirm(SalesOrder $order): SalesOrder
    {
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft orders can be confirmed. Current status: {$order->status}.",
            ]);
        }

        $order->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return $order->fresh(['lines', 'customer']);
    }

    /**
     * Cancel an order (not allowed if delivered or invoiced).
     */
    public function cancel(SalesOrder $order, ?string $reason = null): SalesOrder
    {
        if (in_array($order->status, ['delivered', 'invoiced'], true)) {
            throw ValidationException::withMessages([
                'status' => "Orders with status '{$order->status}' cannot be cancelled.",
            ]);
        }

        if ($order->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Order is already cancelled.',
            ]);
        }

        $order->update([
            'status'              => 'cancelled',
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $order->fresh(['lines', 'customer']);
    }

    // ─── Document Generation ──────────────────────────────────────────────────

    /**
     * Generate an Invoice from a SalesOrder.
     * Updates invoiced_quantity on each order line.
     */
    public function generateInvoice(SalesOrder $order): Invoice
    {
        if (!in_array($order->status, ['confirmed', 'in_progress', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot generate invoice for order with status '{$order->status}'.",
            ]);
        }

        return DB::transaction(function () use ($order): Invoice {
            $order->loadMissing('lines');

            $invoice = Invoice::create([
                'company_id'      => $order->company_id,
                'reference'       => $this->generateInvoiceReference(),
                'sales_order_id'  => $order->id,
                'customer_id'     => $order->customer_id,
                'status'          => 'draft',
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->toDateString(),
                'currency_id'     => $order->currency_id,
                'payment_term_id' => $order->payment_term_id,
                'subtotal_ht'     => $order->subtotal_ht,
                'total_discount'  => $order->total_discount,
                'total_tax'       => $order->total_tax,
                'total_ttc'       => $order->total_ttc,
                'amount_paid'     => 0,
                'amount_due'      => $order->total_ttc,
                'notes'           => $order->notes,
                'created_by'      => auth()->id(),
            ]);

            foreach ($order->lines as $line) {
                $invoice->lines()->create([
                    'company_id'                 => $line->company_id,
                    'product_id'                 => $line->product_id,
                    'description'                => $line->description,
                    'quantity'                   => $line->quantity,
                    'unit_price_ht'              => $line->unit_price_ht,
                    'discount_type'              => $line->discount_type,
                    'discount_value'             => $line->discount_value,
                    'subtotal_ht'                => $line->subtotal_ht,
                    'discount_amount'            => $line->discount_amount,
                    'subtotal_ht_after_discount' => $line->subtotal_ht_after_discount,
                    'tax_id'                     => $line->tax_id,
                    'tax_rate'                   => $line->tax_rate,
                    'tax_amount'                 => $line->tax_amount,
                    'total_ttc'                  => $line->total_ttc,
                    'sort_order'                 => $line->sort_order,
                ]);

                // Mark the full quantity as invoiced
                $line->update([
                    'invoiced_quantity' => $line->quantity,
                ]);
            }

            // Update order amount_invoiced
            $order->update([
                'amount_invoiced' => $order->total_ttc,
            ]);

            return $invoice->fresh(['lines']);
        });
    }

    /**
     * Generate a DeliveryNote from a SalesOrder.
     * Tracks delivered_quantity on each order line.
     */
    public function generateDeliveryNote(SalesOrder $order): DeliveryNote
    {
        if (!in_array($order->status, ['confirmed', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot generate delivery note for order with status '{$order->status}'.",
            ]);
        }

        return DB::transaction(function () use ($order): DeliveryNote {
            $order->loadMissing('lines');

            $deliveryNote = DeliveryNote::create([
                'company_id'     => $order->company_id,
                'reference'      => $this->generateDeliveryNoteReference(),
                'sales_order_id' => $order->id,
                'customer_id'    => $order->customer_id,
                'status'         => 'draft',
                'delivery_date'  => now()->toDateString(),
                'delivery_address' => $order->delivery_address,
                'notes'          => $order->notes,
                'created_by'     => auth()->id(),
            ]);

            foreach ($order->lines as $line) {
                $remaining = (float) $line->remainingToDeliver();

                if ($remaining <= 0) {
                    continue;
                }

                $deliveryNote->lines()->create([
                    'company_id'         => $line->company_id,
                    'sales_order_line_id' => $line->id,
                    'product_id'         => $line->product_id,
                    'description'        => $line->description,
                    'ordered_quantity'   => $line->quantity,
                    'delivered_quantity' => $remaining,
                    'sort_order'         => $line->sort_order,
                ]);

                // Update delivered_quantity on the order line
                $line->update([
                    'delivered_quantity' => $line->quantity,
                ]);
            }

            // Transition order to in_progress if still confirmed
            if ($order->status === 'confirmed') {
                $order->update(['status' => 'in_progress']);
            }

            return $deliveryNote->fresh(['lines']);
        });
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    /**
     * Recalculate and persist subtotal_ht, total_discount, total_tax, total_ttc.
     */
    public function calculateTotals(SalesOrder $order): void
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

    /**
     * Compute all derived amounts for a sales order line.
     *
     * Formula:
     *   subtotal_ht = quantity × unit_price_ht
     *   discount_amount = subtotal_ht × (discount_value / 100)  [if percentage]
     *                   = discount_value                         [if fixed]
     *   subtotal_ht_after_discount = subtotal_ht - discount_amount
     *   tax_amount = subtotal_ht_after_discount × (tax_rate / 100)
     *   total_ttc  = subtotal_ht_after_discount + tax_amount
     */
    public function calculateLineAmounts(array $lineData): array
    {
        $quantity      = (float) ($lineData['quantity'] ?? 0);
        $unitPriceHt   = (float) ($lineData['unit_price_ht'] ?? 0);
        $discountType  = $lineData['discount_type'] ?? 'percentage';
        $discountValue = (float) ($lineData['discount_value'] ?? 0);
        $taxRate       = (float) ($lineData['tax_rate'] ?? 0);

        $subtotalHt = $quantity * $unitPriceHt;

        if ($discountType === 'percentage') {
            $discountAmount = $subtotalHt * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }

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
        $count = SalesOrder::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('BC-%d-%05d', $year, $count);
    }

    private function generateInvoiceReference(): string
    {
        $year  = now()->year;
        $count = Invoice::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('FAC-%d-%05d', $year, $count);
    }

    private function generateDeliveryNoteReference(): string
    {
        $year  = now()->year;
        $count = DeliveryNote::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('BL-%d-%05d', $year, $count);
    }
}
