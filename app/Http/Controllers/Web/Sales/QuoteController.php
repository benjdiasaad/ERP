<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuoteRequest;
use App\Http\Requests\Sales\UpdateQuoteRequest;
use App\Http\Resources\Sales\QuoteResource;
use App\Http\Controllers\Web\Sales\Concerns\ProvidesDocumentOptions;
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    use ProvidesDocumentOptions;

    public const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'];

    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quote::class);

        $filters = $request->only(['search', 'status', 'customer_id']);

        $quotes = Quote::query()
            ->with('customer:id,name,code')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('reference', $like)
                    ->orWhereHas('customer', fn ($query) => $query->whereLike('name', $like)));
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('customer_id', $id))
            ->latest('quote_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $counts = Quote::query()->selectRaw('status, COUNT(*) as aggregate, COALESCE(SUM(total_ttc), 0) as amount')
            ->groupBy('status')->get()->keyBy('status');

        return Inertia::render('Sales/Quotes/Index', [
            'quotes'   => QuoteResource::collection($quotes),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
            'counts'   => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => [
                'count'  => (int) ($counts[$status]->aggregate ?? 0),
                'amount' => round((float) ($counts[$status]->amount ?? 0), 2),
            ]]),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Quote::class);

        return Inertia::render('Sales/Quotes/Create', [
            ...$this->documentOptions(),
            'defaults' => [
                'customer_id'   => $request->integer('customer_id') ?: null,
                'date'          => now()->toDateString(),
                'validity_date' => now()->addDays(30)->toDateString(),
                'currency_id'   => $this->defaultCurrencyId(),
            ],
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $quote = $this->quoteService->create($request->validated());

        return redirect()->route('sales.quotes.show', $quote)->with('success', "Devis {$quote->reference} créé.");
    }

    public function show(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'currency', 'paymentTerm', 'lines.product', 'salesOrder:id,reference,status']);

        return Inertia::render('Sales/Quotes/Show', [
            'quote'      => new QuoteResource($quote),
            'salesOrder' => $quote->salesOrder,
            'isExpired'  => $quote->status === 'sent' && $quote->validity_date?->isPast(),
        ]);
    }

    public function edit(Quote $quote): Response
    {
        $this->authorize('update', $quote);
        $this->ensureDraft($quote);

        $quote->load('lines');

        return Inertia::render('Sales/Quotes/Edit', [
            'quote' => new QuoteResource($quote),
            ...$this->documentOptions(),
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);
        $this->ensureDraft($quote);

        $this->quoteService->update($quote, $request->validated());

        return redirect()->route('sales.quotes.show', $quote)->with('success', 'Devis mis à jour.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        if ($quote->status === 'converted') {
            throw ValidationException::withMessages(['status' => 'Un devis converti en commande ne peut pas être supprimé.']);
        }

        $this->quoteService->delete($quote);

        return redirect()->route('sales.quotes.index')->with('success', "Devis {$quote->reference} supprimé.");
    }

    public function send(Quote $quote): RedirectResponse
    {
        $this->authorize('send', $quote);
        $this->quoteService->send($quote);

        return back()->with('success', 'Devis marqué comme envoyé.');
    }

    public function accept(Quote $quote): RedirectResponse
    {
        $this->authorize('accept', $quote);
        $this->quoteService->accept($quote);

        return back()->with('success', 'Devis accepté.');
    }

    public function reject(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('reject', $quote);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $this->quoteService->reject($quote, $data['reason'] ?? null);

        return back()->with('success', 'Devis refusé.');
    }

    public function convert(Quote $quote): RedirectResponse
    {
        $this->authorize('convert', $quote);

        $order = $this->quoteService->convertToOrder($quote);

        return back()->with('success', "Commande {$order->reference} créée à partir du devis.");
    }

    public function duplicate(Quote $quote): RedirectResponse
    {
        $this->authorize('duplicate', $quote);

        $copy = $this->quoteService->duplicate($quote);

        return redirect()->route('sales.quotes.show', $copy)->with('success', "Copie créée : {$copy->reference}.");
    }

    /**
     * Sent, accepted… quotes are commitments to the customer: only drafts are editable.
     */
    private function ensureDraft(Quote $quote): void
    {
        if ($quote->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Seuls les devis en brouillon peuvent être modifiés. Dupliquez-le pour en créer une nouvelle version.',
            ]);
        }
    }
}
