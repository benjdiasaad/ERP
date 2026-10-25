<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales\Concerns;

use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Finance\Tax;
use App\Models\Inventory\Product;
use App\Models\Sales\Customer;

/**
 * Select options for sales documents with lines (quotes, orders, invoices),
 * limited to the current company by the models' company scope.
 */
trait ProvidesDocumentOptions
{
    protected function documentOptions(): array
    {
        return [
            'customers'    => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'payment_terms_id', 'address', 'city'])
                ->map(fn (Customer $c) => [
                    'value'           => $c->id,
                    'label'           => "{$c->name} ({$c->code})",
                    'payment_term_id' => $c->payment_terms_id,
                    'address'         => trim(implode(', ', array_filter([$c->address, $c->city]))) ?: null,
                ]),
            'products'     => Product::where('is_active', true)->where('is_sellable', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'sale_price', 'tax_rate', 'unit'])
                ->map(fn (Product $p) => [
                    'value'      => $p->id,
                    'label'      => "{$p->code} · {$p->name}",
                    'name'       => $p->name,
                    'sale_price' => (float) $p->sale_price,
                    'tax_rate'   => (float) $p->tax_rate,
                    'unit'       => $p->unit,
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
