<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentMethodRequest;
use App\Http\Requests\Finance\UpdatePaymentMethodRequest;
use App\Http\Resources\Finance\PaymentMethodResource;
use App\Models\Finance\PaymentMethod;
use App\Services\Finance\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly PaymentMethodService $paymentMethodService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PaymentMethod::class);

        $filters = $request->only(['search', 'type', 'is_active', 'sort', 'per_page']);
        $paymentMethods = $this->paymentMethodService->search($filters);

        return PaymentMethodResource::collection($paymentMethods);
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentMethod::class);

        $paymentMethod = $this->paymentMethodService->create($request->validated());

        return (new PaymentMethodResource($paymentMethod))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $this->authorize('view', $paymentMethod);

        return new PaymentMethodResource($paymentMethod);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $this->authorize('update', $paymentMethod);

        $paymentMethod = $this->paymentMethodService->update($paymentMethod, $request->validated());

        return new PaymentMethodResource($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);

        try {
            $this->paymentMethodService->delete($paymentMethod);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }
}
