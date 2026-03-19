<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentTermRequest;
use App\Http\Requests\Finance\UpdatePaymentTermRequest;
use App\Http\Resources\Finance\PaymentTermResource;
use App\Models\Finance\PaymentTerm;
use App\Services\Finance\PaymentTermService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PaymentTermController extends Controller
{
    public function __construct(
        private readonly PaymentTermService $paymentTermService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentTerm::class);

        $filters = $request->only(['search', 'is_active', 'sort', 'per_page']);
        $paymentTerms = $this->paymentTermService->search($filters);

        return PaymentTermResource::collection($paymentTerms);
    }

    public function store(StorePaymentTermRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentTerm::class);

        $paymentTerm = $this->paymentTermService->create($request->validated());

        return (new PaymentTermResource($paymentTerm))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentTerm $paymentTerm): PaymentTermResource
    {
        $this->authorize('view', $paymentTerm);

        return new PaymentTermResource($paymentTerm);
    }

    public function update(UpdatePaymentTermRequest $request, PaymentTerm $paymentTerm): PaymentTermResource
    {
        $this->authorize('update', $paymentTerm);

        $paymentTerm = $this->paymentTermService->update($paymentTerm, $request->validated());

        return new PaymentTermResource($paymentTerm);
    }

    public function destroy(PaymentTerm $paymentTerm): JsonResponse
    {
        $this->authorize('delete', $paymentTerm);

        try {
            $this->paymentTermService->delete($paymentTerm);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }
}
