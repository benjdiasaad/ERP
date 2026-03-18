<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\ReceptionNote;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionNoteService
{
    public function create(array $data): ReceptionNote
    {
        return DB::transaction(function () use ($data): ReceptionNote {
            $data['reference']  = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status']     = 'draft';

            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::findOrFail($data['purchase_order_id']);
            $data['supplier_id'] = $data['supplier_id'] ?? $order->supplier_id;

            $note = ReceptionNote::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $note->lines()->create($lineData);
                }
            }

            return $note->fresh(['lines', 'purchaseOrder', 'supplier']);
        });
    }

    public function update(ReceptionNote $note, array $data): ReceptionNote
    {
        if ($note->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft reception notes can be updated. Current status: {$note->status}.",
            ]);
        }

        return DB::transaction(function () use ($note, $data): ReceptionNote {
            $note->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $note->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;

                    if (!empty($lineData['id'])) {
                        $note->lines()->where('id', $lineData['id'])->update($lineData);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine       = $note->lines()->create($lineData);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $note->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            return $note->fresh(['lines', 'purchaseOrder', 'supplier']);
        });
    }

    public function delete(ReceptionNote $note): void
    {
        if ($note->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft reception notes can be deleted. Current status: {$note->status}.",
            ]);
        }

        $note->delete();
    }

    /**
     * Confirm a reception note: transitions draft → confirmed and updates
     * received_quantity on each linked purchase order line.
     */
    public function confirm(ReceptionNote $note): ReceptionNote
    {
        if ($note->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft reception notes can be confirmed. Current status: {$note->status}.",
            ]);
        }

        return DB::transaction(function () use ($note): ReceptionNote {
            $note->loadMissing(['lines', 'purchaseOrder']);

            // Update received_quantity on each linked purchase order line
            foreach ($note->lines as $line) {
                if ($line->purchase_order_line_id) {
                    $orderLine = $line->purchaseOrderLine;
                    if ($orderLine) {
                        $newReceived = bcadd(
                            (string) $orderLine->received_quantity,
                            (string) $line->received_quantity,
                            4
                        );
                        $orderLine->update(['received_quantity' => $newReceived]);
                    }
                }
            }

            // Transition purchase order to in_progress if still confirmed
            $order = $note->purchaseOrder;
            if ($order && $order->status === 'confirmed') {
                $order->update(['status' => 'in_progress']);
            }

            // Check if all lines are fully received → mark order as received
            if ($order) {
                $order->loadMissing('lines');
                $allReceived = $order->lines->every(function ($orderLine) {
                    return bccomp(
                        (string) $orderLine->received_quantity,
                        (string) $orderLine->quantity,
                        4
                    ) >= 0;
                });

                if ($allReceived && in_array($order->status, ['in_progress', 'confirmed'], true)) {
                    $order->update(['status' => 'received']);
                }
            }

            $note->update([
                'status'       => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]);

            return $note->fresh(['lines', 'purchaseOrder', 'supplier']);
        });
    }

    public function cancel(ReceptionNote $note, ?string $reason = null): ReceptionNote
    {
        if ($note->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Reception note is already cancelled.',
            ]);
        }

        if ($note->status === 'confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Confirmed reception notes cannot be cancelled.',
            ]);
        }

        $note->update([
            'status'              => 'cancelled',
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $note->fresh(['lines', 'purchaseOrder', 'supplier']);
    }

    // ─── Reference Generator ──────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = ReceptionNote::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('BR-%d-%05d', $year, $count);
    }
}
