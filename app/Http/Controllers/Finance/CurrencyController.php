<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCurrencyRequest;
use App\Http\Requests\Finance\UpdateCurrencyRequest;
use App\Http\Resources\Finance\CurrencyResource;
use App\Models\Finance\Currency;
use App\Services\Finance\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        $filters = $request->only(['search', 'is_active', 'sort', 'per_page']);
        $currencies = $this->currencyService->search($filters);

        return CurrencyResource::collection($currencies);
    }

    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $this->authorize('create', Currency::class);

        $currency = $this->currencyService->create($request->validated());

        return (new CurrencyResource($currency))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Currency $currency): CurrencyResource
    {
        $this->authorize('view', $currency);

        return new CurrencyResource($currency);
    }

    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        $currency = $this->currencyService->update($currency, $request->validated());

        return new CurrencyResource($currency);
    }

    public function destroy(Currency $currency): JsonResponse
    {
        $this->authorize('delete', $currency);

        try {
            $this->currencyService->delete($currency);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }
}
