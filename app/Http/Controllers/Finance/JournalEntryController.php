<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreJournalEntryRequest;
use App\Http\Requests\Finance\UpdateJournalEntryRequest;
use App\Http\Resources\Finance\JournalEntryResource;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\JournalEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', JournalEntry::class);

        $journalEntries = JournalEntry::with(['lines.chartOfAccount'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return JournalEntryResource::collection($journalEntries);
    }

    public function store(StoreJournalEntryRequest $request): JsonResponse
    {
        $this->authorize('create', JournalEntry::class);

        $journalEntry = $this->journalEntryService->create($request->validated());

        return (new JournalEntryResource($journalEntry))
            ->response()
            ->setStatusCode(201);
    }

    public function show(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('view', $journalEntry);

        $journalEntry->load(['lines.chartOfAccount', 'postedBy']);

        return new JournalEntryResource($journalEntry);
    }

    public function update(UpdateJournalEntryRequest $request, JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('update', $journalEntry);

        $journalEntry = $this->journalEntryService->update($journalEntry, $request->validated());

        return new JournalEntryResource($journalEntry);
    }

    public function destroy(JournalEntry $journalEntry): JsonResponse
    {
        $this->authorize('delete', $journalEntry);

        try {
            $this->journalEntryService->delete($journalEntry);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function post(JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('post', $journalEntry);

        try {
            $journalEntry = $this->journalEntryService->post($journalEntry);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new JournalEntryResource($journalEntry);
    }

    public function cancel(Request $request, JournalEntry $journalEntry): JournalEntryResource
    {
        $this->authorize('cancel', $journalEntry);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $journalEntry = $this->journalEntryService->cancel($journalEntry, $request->reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new JournalEntryResource($journalEntry);
    }
}
