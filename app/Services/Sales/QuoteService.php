<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Sales\Quote;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    /**
     * Create a new quote with lines and auto-generated reference.
     */
    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data): Quote {
            $data['reference'] = $this->generateReference();
            $data['created_by'] = auth()->id();
            $data['status'] = $data['status'] ?? 'draft';

            // Map request field 'date' to model column 'quote_date'
            if (isset($data['date']) && !isset($data['quote_date'])) {
                $data['quote_date'] = $data['date'];
                unset($data['date']);
            }

            $quote = Quote::create(Arr::except($data, ['lines']));

            if (!empty($data['lines'])) {
                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $line = $this->calculateLineAmounts($lineData);
                    $quote->lines()->create($line);
                }
            }

            $this->calculateTotals($quote);

            return $quote->fresh(['lines', 'customer']);
        });
    }

    /**
     * Update an existing quote and sync its lines.
     */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data): Quote {
            $data['updated_by'] = auth()->id();

            // Map request field 'date' to model column 'quote_date'
            if (isset($data['date']) && !isset($data['quote_date'])) {
                $data['quote_date'] = $data['date'];
                unset($data['date']);
            }

            $quote->update(Arr::except($data, ['lines']));

            if (array_key_exists('lines', $data)) {
                $existingIds = $quote->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $lineData['sort_order'] = $lineData['sort_order'] ?? $index;
                    $calculated = $this->calculateLineAmounts($lineData);

                    if (!empty($lineData['id'])) {
                        $quote->lines()->where('id', $lineData['id'])->update($calculated);
                        $incomingIds[] = $lineData['id'];
                    } else {
                        $newLine = $quote->lines()->create($calculated);
                        $incomingIds[] = $newLine->id;
                    }
                }

                // Remove lines that were not in the incoming payload
                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $quote->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            $this->calculateTotals($quote);

            return $quote->fresh(['lines', 'customer']);
        });
    }

    /**
     * Soft-delete a quote.
     */
    public function delete(Quote $quote): void
    {
        $quote->delete();
    }

    /**
     * Transition quote from draft → sent.
     */
    public function send(Quote $quote): Quote
    {
        if ($quote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft quotes can be sent. Current status: {$quote->status}.",
            ]);
        }

        $quote->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        return $quote->fresh(['lines', 'customer']);
    }

    /**
     * Transition quote from sent → accepted.
     */
    public function accept(Quote $quote): Quote
    {
        if ($quote->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => "Only sent quotes can be accepted. Current status: {$quote->status}.",
            ]);
        }

        $quote->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        return $quote->fresh(['lines', 'customer']);
    }

    /**
     * Transition quote from sent → rejected.
     */
    public function reject(Quote $quote, ?string $reason = null): Quote
    {
        if ($quote->status !== 'sent') {
            throw ValidationException::withMessages([
                'status' => "Only sent quotes can be rejected. Current status: {$quote->status}.",
            ]);
        }

        $quote->update([
            'status'           => 'rejected',
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $quote->fresh(['lines', 'customer']);
    }

    /**
     * Clone a quote (with all its lines) as a new draft with a new reference.
     */
    public function duplicate(Quote $quote): Quote
    {
        return DB::transaction(function () use ($quote): Quote {
            $quoteData = $quote->toArray();

            $newQuote = Quote::create([
                'company_id'          => $quoteData['company_id'],
                'customer_id'         => $quoteData['customer_id'],
                'reference'           => $this->generateReference(),
                'quote_date'          => now()->toDateString(),
                'validity_date'       => $quoteData['validity_date'] ?? null,
                'status'              => 'draft',
                'currency_id'         => $quoteData['currency_id'] ?? null,
                'payment_term_id'     => $quoteData['payment_term_id'] ?? null,
                'notes'               => $quoteData['notes'] ?? null,
                'terms_and_conditions' => $quoteData['terms_and_conditions'] ?? null,
                'subtotal_ht'         => 0,
                'total_discount'      => 0,
                'total_tax'           => 0,
                'total_ttc'           => 0,
                'created_by'          => auth()->id(),
            ]);

            foreach ($quote->lines as $line) {
                $lineData = $line->toArray();
                $newQuote->lines()->create(Arr::except($lineData, ['id', 'quote_id', 'created_at', 'updated_at']));
            }

            $this->calculateTotals($newQuote);

            return $newQuote->fresh(['lines', 'customer']);
        });
    }

    /**
     * Convert an accepted quote into a SalesOrder.
     */
    public function convertToOrder(Quote $quote): SalesOrder
    {
        if ($quote->status !== 'accepted') {
            throw ValidationException::withMessages([
                'status' => "Only accepted quotes can be converted to orders. Current status: {$quote->status}.",
            ]);
        }

        return DB::transaction(function () use ($quote): SalesOrder {
            $order = SalesOrder::create([
                'company_id'      => $quote->company_id,
                'customer_id'     => $quote->customer_id,
                'reference'       => $this->generateOrderReference(),
                'quote_id'        => $quote->id,
                'order_date'      => now()->toDateString(),
                'status'          => 'draft',
                'currency_id'     => $quote->currency_id,
                'payment_term_id' => $quote->payment_term_id,
                'subtotal_ht'     => $quote->subtotal_ht,
                'total_discount'  => $quote->total_discount,
                'total_tax'       => $quote->total_tax,
                'total_ttc'       => $quote->total_ttc,
                'notes'           => $quote->notes,
                'terms_and_conditions' => $quote->terms_and_conditions,
                'created_by'      => auth()->id(),
            ]);

            foreach ($quote->lines as $line) {
                $order->lines()->create([
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
            }

            $quote->update([
                'status'               => 'converted',
                'converted_to_order_id' => $order->id,
            ]);

            return $order->fresh(['lines', 'customer']);
        });
    }

    /**
     * Recalculate and persist subtotal_ht, total_discount, total_tax, total_ttc.
     */
    public function calculateTotals(Quote $quote): void
    {
        $quote->loadMissing('lines');

        $subtotalHt    = 0.0;
        $totalDiscount = 0.0;
        $totalTax      = 0.0;

        foreach ($quote->lines as $line) {
            $subtotalHt    += (float) $line->subtotal_ht;
            $totalDiscount += (float) $line->discount_amount;
            $totalTax      += (float) $line->tax_amount;
        }

        // Add document-level discount if present
        $docDiscount = (float) ($quote->document_discount ?? 0);
        $totalDiscount += $docDiscount;

        $totalTtc = $subtotalHt - $totalDiscount + $totalTax;

        $quote->update([
            'subtotal_ht'    => round($subtotalHt, 2),
            'total_discount' => round($totalDiscount, 2),
            'total_tax'      => round($totalTax, 2),
            'total_ttc'      => round($totalTtc, 2),
        ]);
    }

    /**
     * Compute all derived amounts for a quote line.
     *
     * @param  array  $lineData  Raw line input (quantity, unit_price_ht, discount_type, discount_value, tax_rate)
     * @return array             Line data enriched with calculated fields
     */
    public function calculateLineAmounts(array $lineData): array
    {
        $quantity     = (float) ($lineData['quantity'] ?? 0);
        $unitPriceHt  = (float) ($lineData['unit_price_ht'] ?? 0);
        $discountType = $lineData['discount_type'] ?? 'percentage';
        $discountValue = (float) ($lineData['discount_value'] ?? 0);
        $taxRate      = (float) ($lineData['tax_rate'] ?? 0);

        $subtotalHt = $quantity * $unitPriceHt;

        if ($discountType === 'percentage') {
            $discountAmount = $subtotalHt * ($discountValue / 100);
        } else {
            $discountAmount = $discountValue;
        }

        $subtotalHtAfterDiscount = $subtotalHt - $discountAmount;
        $taxAmount  = $subtotalHtAfterDiscount * ($taxRate / 100);
        $totalTtc   = $subtotalHtAfterDiscount + $taxAmount;

        return array_merge($lineData, [
            'subtotal_ht'                => round($subtotalHt, 2),
            'discount_amount'            => round($discountAmount, 2),
            'subtotal_ht_after_discount' => round($subtotalHtAfterDiscount, 2),
            'tax_amount'                 => round($taxAmount, 2),
            'total_ttc'                  => round($totalTtc, 2),
        ]);
    }

    /**
     * Generate a PDF for the quote and return the stored file path.
     *
     * Uses barryvdh/laravel-dompdf when available; falls back to a stub path.
     */
    public function generatePdf(Quote $quote): string
    {
        $quote->loadMissing(['lines', 'customer', 'currency', 'paymentTerm', 'createdBy']);

        $filename = 'quotes/' . $quote->reference . '.pdf';
        $storagePath = storage_path('app/public/' . $filename);

        // Ensure directory exists
        if (!is_dir(dirname($storagePath))) {
            mkdir(dirname($storagePath), 0755, true);
        }

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quotes.show', ['quote' => $quote]);
            $pdf->save($storagePath);
        } else {
            // Stub: return path without generating actual file
            return $filename;
        }

        return $filename;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = Quote::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('DEV-%d-%05d', $year, $count);
    }

    private function generateOrderReference(): string
    {
        $year  = now()->year;
        $count = SalesOrder::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('BC-%d-%05d', $year, $count);
    }
}
