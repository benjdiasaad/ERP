<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use App\Models\Event\Event;
use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use App\Services\CRM\ActivityService;
use App\Services\CRM\OpportunityService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OpportunityService $opportunityService,
        private readonly ActivityService $activityService,
    ) {}

    /**
     * Each block is only computed when the user may see that module.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $can = fn (string $slug) => $user->hasPermission($slug);

        $stats = [];

        if ($can('customers.view_any')) {
            $stats[] = ['label' => 'Clients actifs', 'value' => Customer::where('is_active', true)->count(), 'format' => 'number'];
        }

        if ($can('quotes.view_any')) {
            $stats[] = [
                'label'  => 'Devis en cours',
                'value'  => (float) Quote::whereIn('status', ['draft', 'sent'])->sum('total_ttc'),
                'format' => 'currency',
                'hint'   => Quote::whereIn('status', ['draft', 'sent'])->count() . ' devis',
            ];
        }

        $pipeline = null;
        if ($can('opportunities.view_any')) {
            $pipeline = $this->opportunityService->pipeline();
            $stats[] = [
                'label'  => 'Pipeline pondéré',
                'value'  => $pipeline['open_weighted_value'],
                'format' => 'currency',
                'hint'   => $pipeline['open_count'] . ' opportunités ouvertes',
            ];
        }

        if ($can('leads.view_any')) {
            $stats[] = ['label' => 'Nouveaux leads', 'value' => Lead::whereIn('status', ['new', 'contacted'])->count(), 'format' => 'number'];
        }

        return Inertia::render('Dashboard', [
            'stats'    => $stats,
            'pipeline' => $pipeline ? collect($pipeline['stages'])
                ->only(Opportunity::OPEN_STAGES)
                ->map(fn ($stage, $key) => ['stage' => $key, ...$stage])
                ->values() : null,
            'activities' => $can('activities.view_any')
                ? [
                    'overdue'  => $this->activityRows($this->activityService->getOverdue()->take(5)),
                    'upcoming' => $this->activityRows($this->activityService->getUpcoming(7)->take(5)),
                ]
                : null,
            'events' => $can('events.view_any')
                ? Event::where('start_date', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->orderBy('start_date')
                    ->limit(5)
                    ->get(['id', 'title', 'type', 'location', 'start_date'])
                : null,
        ]);
    }

    private function activityRows($activities): array
    {
        return $activities->map(fn (Activity $activity) => [
            'id'       => $activity->id,
            'type'     => $activity->type,
            'subject'  => $activity->subject,
            'due_date' => $activity->due_date?->toISOString(),
            'priority' => $activity->priority,
            'assignee' => $activity->assignee?->name,
        ])->values()->all();
    }
}
