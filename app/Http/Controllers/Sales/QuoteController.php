<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreQuoteRequest;
use App\Http\Requests\Sales\UpdateQuoteRequest;
use App\Http\Resources\Sales\QuoteResource;
use App\Models\Sales\Quote;
use App\Services\Sales\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Quote::class);

        $quotes = Quote::with(['customer', 'currency', 'lines'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return QuoteResource::collection($quotes);
    }

    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $quote = $this->quoteService->create($request->validated());

        return (new QuoteResource($quote))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Quote $quote): QuoteResource
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'currency', 'paymentTerm', 'lines', 'createdBy']);

        return new QuoteResource($quote);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): QuoteResource
    {
        $this->authorize('update', $quote);

        $quote = $this->quoteService->update($quote, $request->validated());

        return new QuoteResource($quote);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        try {
            $this->quoteService->delete($quote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function send(Quote $quote): QuoteResource
    {
        $this->authorize('send', $quote);

        try {
            $quote = $this->quoteService->send($quote);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new QuoteResource($quote);
    }

    public function accept(Quote $quote): QuoteResource
    {
        $this->authorize('update', $quote);

        try {
            $quote = $this->quoteService->accept($quote);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new QuoteResource($quote);
    }

    public function reject(Request $request, Quote $quote): QuoteResource
    {
        $this->authorize('update', $quote);

        $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $quote = $this->quoteService->reject($quote, $request->rejection_reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new QuoteResource($quote);
    }

    public function duplicate(Quote $quote): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $newQuote = $this->quoteService->duplicate($quote);

        return (new QuoteResource($newQuote))
            ->response()
            ->setStatusCode(201);
    }

    public function convertToOrder(Quote $quote): JsonResponse
    {
        $this->authorize('convert', $quote);

        try {
            $order = $this->quoteService->convertToOrder($quote);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json([
            'message'      => 'Quote successfully converted to sales order.',
            'sales_order'  => $order,
        ], 201);
    }

    public function pdf(Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $path = $this->quoteService->generatePdf($quote);

        return response()->json([
            'path' => $path,
            'url'  => asset('storage/' . $path),
        ]);
    }
}
