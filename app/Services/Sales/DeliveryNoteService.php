<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\DeliveryNote;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryNoteService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): DeliveryNote
    {
        return DB::transaction(function () use ($data): DeliveryNote {
            $data['reference']  = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status']     = $data['status'] ?? 'draft';
            $data['date']       = $data['date'] ?? now()->toDateString();

            $deliveryNote = DeliveryNote::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $deliveryNote->lines()->create($lineData);
                }
            }

            return $deliveryNote->fresh(['lines', 'customer', 'salesOrder']);
        });
    }

    public function update(DeliveryNote $deliveryNote, array $data): DeliveryNote
    {
        if ($deliveryNote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft delivery notes can be updated. Current status: {$deliveryNote->status}.",
            ]);
        }

        return DB::transaction(function () use ($deliveryNote, $data): DeliveryNote {
            $deliveryNote->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $deliveryNote->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;

                    if (!empty($lineData['id'])) {
                        $deliveryNote->lines()->where('id', $lineData['id'])->update($lineData);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine = $deliveryNote->lines()->create($lineData);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $deliveryNote->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            return $deliveryNote->fresh(['lines', 'customer', 'salesOrder']);
        });
    }

    public function delete(DeliveryNote $deliveryNote): void
    {
        if ($deliveryNote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft delivery notes can be deleted. Current status: {$deliveryNote->status}.",
            ]);
        }

        $deliveryNote->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Transition ready → shipped. Optionally set carrier/tracking info.
     */
    public function ship(DeliveryNote $deliveryNote, array $data = []): DeliveryNote
    {
        if ($deliveryNote->status !== 'ready') {
            throw ValidationException::withMessages([
                'status' => "Only ready delivery notes can be shipped. Current status: {$deliveryNote->status}.",
            ]);
        }

        $deliveryNote->update([
            'status'          => 'shipped',
            'shipped_at'      => $data['shipped_at'] ?? now()->toDateString(),
            'carrier'         => $data['carrier'] ?? $deliveryNote->carrier,
            'tracking_number' => $data['tracking_number'] ?? $deliveryNote->tracking_number,
            'shipped_by'      => auth()->id(),
        ]);

        return $deliveryNote->fresh(['lines', 'customer', 'salesOrder']);
    }

    /**
     * Transition shipped → delivered.
     * Updates delivered_quantity on each linked SalesOrderLine.
     * If all order lines are fully delivered, transitions SalesOrder to 'delivered'.
     */
    public function deliver(DeliveryNote $deliveryNote): DeliveryNote
    {
        if ($deliveryNote->status !== 'shipped') {
            throw ValidationException::withMessages([
                'status' => "Only shipped delivery notes can be marked as delivered. Current status: {$deliveryNote->status}.",
            ]);
        }

        return DB::transaction(function () use ($deliveryNote): DeliveryNote {
            $deliveryNote->update([
                'status'       => 'delivered',
                'delivered_at' => now()->toDateString(),
                'delivered_by' => auth()->id(),
            ]);

            $deliveryNote->loadMissing('lines');

            foreach ($deliveryNote->lines as $line) {
                if (!$line->sales_order_line_id) {
                    continue;
                }

                $orderLine = $line->salesOrderLine;
                if (!$orderLine) {
                    continue;
                }

                $newDeliveredQty = round(
                    (float) $orderLine->delivered_quantity + (float) $line->shipped_quantity,
                    4
                );

                $orderLine->update([
                    'delivered_quantity' => $newDeliveredQty,
                ]);
            }

            // Check if the linked sales order is fully delivered
            if ($deliveryNote->sales_order_id) {
                $this->checkAndUpdateOrderDeliveryStatus($deliveryNote->salesOrder);
            }

            return $deliveryNote->fresh(['lines', 'customer', 'salesOrder']);
        });
    }

    /**
     * Transition delivered → returned.
     * Reverses the delivered_quantity updates on linked SalesOrderLines.
     */
    public function return(DeliveryNote $deliveryNote, string $reason = ''): DeliveryNote
    {
        if ($deliveryNote->status !== 'delivered') {
            throw ValidationException::withMessages([
                'status' => "Only delivered delivery notes can be returned. Current status: {$deliveryNote->status}.",
            ]);
        }

        return DB::transaction(function () use ($deliveryNote, $reason): DeliveryNote {
            $deliveryNote->update([
                'status' => 'returned',
                'notes'  => $reason
                    ? trim(($deliveryNote->notes ?? '') . "\nReturn reason: {$reason}")
                    : $deliveryNote->notes,
            ]);

            $deliveryNote->loadMissing('lines');

            foreach ($deliveryNote->lines as $line) {
                if (!$line->sales_order_line_id) {
                    continue;
                }

                $orderLine = $line->salesOrderLine;
                if (!$orderLine) {
                    continue;
                }

                $newDeliveredQty = round(
                    max(0, (float) $orderLine->delivered_quantity - (float) $line->shipped_quantity),
                    4
                );

                $orderLine->update([
                    'delivered_quantity' => $newDeliveredQty,
                ]);
            }

            return $deliveryNote->fresh(['lines', 'customer', 'salesOrder']);
        });
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Check if all lines of the sales order are fully delivered.
     * If so, transition the order status to 'delivered'.
     */
    private function checkAndUpdateOrderDeliveryStatus(SalesOrder $order): void
    {
        $order->loadMissing('lines');

        $allDelivered = $order->lines->every(function ($line) {
            return bccomp(
                (string) $line->delivered_quantity,
                (string) $line->quantity,
                4
            ) >= 0;
        });

        if ($allDelivered && in_array($order->status, ['confirmed', 'in_progress'], true)) {
            $order->update(['status' => 'delivered']);
        }
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = DeliveryNote::withoutGlobalScopes()->whereYear('created_at', $year)->count() + 1;

        return sprintf('BL-%d-%05d', $year, $count);
    }
}
