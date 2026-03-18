<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Models\Purchasing\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    /**
     * Paginated list of purchase requests with optional filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseRequest::with(['lines', 'supplier']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        $perPage = (int) ($filters['paginate'] ?? 15);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new purchase request with lines and auto-generated reference.
     */
    public function create(array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($data): PurchaseRequest {
            $data['reference']    = $this->generateReference();
            $data['created_by']   = auth()->id();
            $data['requested_by'] = $data['requested_by'] ?? auth()->id();
            $data['status']       = $data['status'] ?? 'draft';

            $pr = PurchaseRequest::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $lineData['estimated_total'] = $this->calculateLineTotal($lineData);
                    $pr->lines()->create($lineData);
                }
            }

            return $pr->fresh(['lines', 'supplier']);
        });
    }

    /**
     * Update a purchase request and sync its lines. Only allowed on draft status.
     */
    public function update(PurchaseRequest $pr, array $data): PurchaseRequest
    {
        if ($pr->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase requests can be updated. Current status: {$pr->status}.",
            ]);
        }

        return DB::transaction(function () use ($pr, $data): PurchaseRequest {
            $data['updated_by'] = auth()->id();

            $pr->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $pr->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order']      = $lineData['sort_order'] ?? $index;
                    $lineData['estimated_total'] = $this->calculateLineTotal($lineData);

                    if (!empty($lineData['id'])) {
                        $pr->lines()->where('id', $lineData['id'])->update($lineData);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine       = $pr->lines()->create($lineData);
                        $incomingIds[] = $newLine->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $pr->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            return $pr->fresh(['lines', 'supplier']);
        });
    }

    /**
     * Soft-delete a purchase request. Only allowed on draft or cancelled status.
     */
    public function delete(PurchaseRequest $pr): void
    {
        if (!in_array($pr->status, ['draft', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "Only draft or cancelled purchase requests can be deleted. Current status: {$pr->status}.",
            ]);
        }

        $pr->delete();
    }

    /**
     * Transition purchase request from draft → submitted.
     */
    public function submit(PurchaseRequest $pr): PurchaseRequest
    {
        if ($pr->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft purchase requests can be submitted. Current status: {$pr->status}.",
            ]);
        }

        $pr->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return $pr->fresh(['lines', 'supplier']);
    }

    /**
     * Transition purchase request from submitted → approved.
     */
    public function approve(PurchaseRequest $pr): PurchaseRequest
    {
        if ($pr->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => "Only submitted purchase requests can be approved. Current status: {$pr->status}.",
            ]);
        }

        $pr->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $pr->fresh(['lines', 'supplier']);
    }

    /**
     * Transition purchase request from submitted → rejected.
     */
    public function reject(PurchaseRequest $pr, ?string $reason = null): PurchaseRequest
    {
        if ($pr->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => "Only submitted purchase requests can be rejected. Current status: {$pr->status}.",
            ]);
        }

        $pr->update([
            'status'           => 'rejected',
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $pr->fresh(['lines', 'supplier']);
    }

    /**
     * Cancel a purchase request (draft, submitted, or approved → cancelled).
     */
    public function cancel(PurchaseRequest $pr): PurchaseRequest
    {
        if (!in_array($pr->status, ['draft', 'submitted', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => "Purchase requests with status '{$pr->status}' cannot be cancelled.",
            ]);
        }

        $pr->update(['status' => 'cancelled']);

        return $pr->fresh(['lines', 'supplier']);
    }

    /**
     * Convert an approved purchase request into a PurchaseOrder (stub).
     *
     * TODO: Implement full PurchaseOrder creation once App\Models\Purchasing\PurchaseOrder is available.
     */
    public function convertToOrder(PurchaseRequest $pr): \App\Models\Purchasing\PurchaseOrder
    {
        if ($pr->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => "Only approved purchase requests can be converted to orders. Current status: {$pr->status}.",
            ]);
        }

        return DB::transaction(function () use ($pr): \App\Models\Purchasing\PurchaseOrder {
            // TODO: Replace with proper reference generation once PurchaseOrder is fully implemented.
            $order = \App\Models\Purchasing\PurchaseOrder::create([
                'company_id'          => $pr->company_id,
                'supplier_id'         => $pr->supplier_id,
                'purchase_request_id' => $pr->id,
                'reference'           => $this->generateOrderReference(),
                'order_date'          => now()->toDateString(),
                'status'              => 'draft',
                'notes'               => $pr->notes,
                'created_by'          => auth()->id(),
            ]);

            foreach ($pr->lines as $line) {
                $order->lines()->create([
                    'company_id'           => $line->company_id,
                    'product_id'           => $line->product_id,
                    'description'          => $line->description,
                    'quantity'             => $line->quantity,
                    'unit'                 => $line->unit,
                    'estimated_unit_price' => $line->estimated_unit_price,
                    'estimated_total'      => $line->estimated_total,
                    'notes'                => $line->notes,
                    'sort_order'           => $line->sort_order,
                ]);
            }

            $pr->update(['status' => 'converted']);

            return $order->fresh(['lines', 'supplier']);
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = PurchaseRequest::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('DA-%d-%05d', $year, $count);
    }

    private function generateOrderReference(): string
    {
        $year  = now()->year;
        // TODO: Use PurchaseOrder model directly once available.
        $count = \App\Models\Purchasing\PurchaseOrder::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('PO-%d-%05d', $year, $count);
    }

    private function calculateLineTotal(array $lineData): float
    {
        $quantity           = (float) ($lineData['quantity'] ?? 0);
        $estimatedUnitPrice = (float) ($lineData['estimated_unit_price'] ?? 0);

        return round($quantity * $estimatedUnitPrice, 2);
    }
}
