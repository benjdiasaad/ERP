<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreContractRequest;
use App\Models\Personnel\Contract;
use App\Services\Personnel\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Contracts are created and terminated from the personnel page.
 */
class ContractController extends Controller
{
    public function __construct(
        private readonly ContractService $contractService,
    ) {}

    public function store(StoreContractRequest $request): RedirectResponse
    {
        $contract = $this->contractService->create($request->validated());

        return back()->with('success', "Contrat {$contract->reference} créé.");
    }

    public function terminate(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'termination_date' => ['required', 'date', 'after_or_equal:' . $contract->start_date?->toDateString()],
            'reason'           => ['required', 'string', 'max:1000'],
        ]);

        $this->contractService->terminate($contract, $data['termination_date'], $data['reason']);

        return back()->with('success', 'Contrat résilié.');
    }
}
