<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Personnel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personnel\StoreLeaveRequest;
use App\Http\Resources\Personnel\LeaveResource;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use App\Services\Personnel\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Leave requests: pending → approved / rejected (or cancelled by the requester).
 */
class LeaveController extends Controller
{
    private const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public function __construct(
        private readonly LeaveService $leaveService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'leave_type']);

        $leaves = Leave::query()
            ->with('personnel:id,first_name,last_name,matricule')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('personnel', fn ($query) => $query
                ->whereLike('first_name', "%{$search}%")->orWhereLike('last_name', "%{$search}%")->orWhereLike('matricule', "%{$search}%")))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['leave_type'] ?? null, fn ($query, $type) => $query->where('leave_type', $type))
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        $counts = Leave::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Personnel/Leaves/Index', [
            'leaves'     => LeaveResource::collection($leaves),
            'counts'     => collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)]),
            'personnels' => Personnel::where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'matricule'])
                ->map(fn (Personnel $p) => ['value' => $p->id, 'label' => "{$p->last_name} {$p->first_name} ({$p->matricule})"]),
            'filters'    => $filters,
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $this->leaveService->create($request->validated());

        return back()->with('success', 'Demande de congé enregistrée.');
    }

    public function approve(Leave $leave): RedirectResponse
    {
        $this->leaveService->approve($leave, (int) auth()->id());

        return back()->with('success', 'Congé approuvé.');
    }

    public function reject(Request $request, Leave $leave): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->leaveService->reject($leave, (int) auth()->id(), $data['reason']);

        return back()->with('success', 'Congé refusé.');
    }

    public function cancel(Leave $leave): RedirectResponse
    {
        $this->leaveService->cancel($leave);

        return back()->with('success', 'Demande annulée.');
    }

    public function destroy(Leave $leave): RedirectResponse
    {
        $leave->delete();

        return back()->with('success', 'Demande supprimée.');
    }
}
