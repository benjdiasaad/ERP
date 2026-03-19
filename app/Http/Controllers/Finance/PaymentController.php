<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Http\Requests\Finance\UpdatePaymentRequest;
use App\Http\Resources\Finance\PaymentResource;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::with(['payable', 'paymentMethod', 'bankAccount'])
            ->when($request->search, fn ($q) => $q->where('reference', 'like', "%{$request->search}%"))
            ->when($request->direction, fn ($q) => $q->where('direction', $request->direction))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $this->authorize('create', Payment::class);

        $payment = $this->paymentService->create($request->validated());

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Payment $payment): PaymentResource
    {
        $this->authorize('view', $payment);

        $payment->load(['payable', 'paymentMethod', 'bankAccount', 'confirmedBy']);

        return new PaymentResource($payment);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $this->authorize('update', $payment);

        $payment = $this->paymentService->update($payment, $request->validated());

        return new PaymentResource($payment);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);

        try {
            $this->paymentService->delete($payment);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function confirm(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('confirm', $payment);

        $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payment = $this->paymentService->confirm($payment, $request->notes);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PaymentResource($payment);
    }

    public function cancel(Request $request, Payment $payment): PaymentResource
    {
        $this->authorize('cancel', $payment);

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $payment = $this->paymentService->cancel($payment, $request->reason);
        } catch (ValidationException $e) {
            abort(422, $e->getMessage());
        }

        return new PaymentResource($payment);
    }
}
