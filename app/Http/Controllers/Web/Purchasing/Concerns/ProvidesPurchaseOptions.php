<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Purchasing\Concerns;

use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Finance\Tax;
use App\Models\Inventory\Product;
use App\Models\Purchasing\Supplier;

/**
 * Select options for purchasing documents, limited to the current company by
 * the models' company scope. Products carry their purchase price as `price`.
 */
trait ProvidesPurchaseOptions
{
    protected function purchaseOptions(): array
    {
        return [
            'suppliers'    => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'payment_term_id'])
                ->map(fn (Supplier $s) => ['value' => $s->id, 'label' => "{$s->name} ({$s->code})", 'payment_term_id' => $s->payment_term_id]),
            'products'     => Product::where('is_active', true)->where('is_purchasable', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'purchase_price', 'tax_rate', 'unit'])
                ->map(fn (Product $p) => [
                    'value'    => $p->id,
                    'label'    => "{$p->code} · {$p->name}",
                    'name'     => $p->name,
                    'price'    => (float) $p->purchase_price,
                    'tax_rate' => (float) $p->tax_rate,
                    'unit'     => $p->unit,
                ]),
            'taxes'        => Tax::where('is_active', true)->orderByDesc('rate')->get(['id', 'name', 'rate'])
                ->map(fn (Tax $t) => ['value' => $t->id, 'label' => $t->name, 'rate' => (float) $t->rate]),
            'paymentTerms' => PaymentTerm::where('is_active', true)->orderBy('days')->get(['id', 'name', 'days'])
                ->map(fn (PaymentTerm $t) => ['value' => $t->id, 'label' => $t->name, 'days' => (int) $t->days]),
            'currencies'   => Currency::orderBy('code')->get(['id', 'code'])
                ->map(fn (Currency $c) => ['value' => $c->id, 'label' => $c->code]),
        ];
    }

    protected function defaultCurrencyId(): ?int
    {
        return Currency::where('code', 'MAD')->value('id');
    }
}
