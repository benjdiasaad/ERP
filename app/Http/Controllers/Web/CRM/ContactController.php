<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreContactRequest;
use App\Http\Requests\CRM\UpdateContactRequest;
use App\Http\Resources\CRM\ActivityResource;
use App\Http\Resources\CRM\ContactResource;
use App\Models\CRM\Activity;
use App\Models\CRM\Contact;
use App\Models\Purchasing\Supplier;
use App\Models\Sales\Customer;
use App\Services\CRM\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        $filters = $request->only(['search', 'status']);

        $contacts = Contact::query()
            ->with('customer')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%' . $search . '%';
                $query->where(fn ($query) => $query
                    ->whereLike('first_name', $like)
                    ->orWhereLike('last_name', $like)
                    ->orWhereLike('email', $like)
                    ->orWhereLike('organization', $like));
            })
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('CRM/Contacts/Index', [
            'contacts' => ContactResource::collection($contacts),
            'filters'  => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Contact::class);

        return Inertia::render('CRM/Contacts/Create', $this->formOptions());
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $contact = $this->contactService->create($request->validated());

        return redirect()->route('crm.contacts.show', $contact)->with('success', 'Contact créé.');
    }

    public function show(Contact $contact): Response
    {
        $this->authorize('view', $contact);

        $contact->load(['customer', 'supplier']);

        return Inertia::render('CRM/Contacts/Show', [
            'contact'       => new ContactResource($contact),
            'leads'         => $contact->leads()->latest()->get(['id', 'reference', 'title', 'status', 'estimated_value']),
            'opportunities' => $contact->opportunities()->latest()->get(['id', 'reference', 'title', 'stage', 'value']),
            'activities'    => ActivityResource::collection(
                Activity::where('related_type', Contact::class)->where('related_id', $contact->id)
                    ->with('assignee')->orderByDesc('due_date')->get()
            ),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        $this->authorize('update', $contact);

        return Inertia::render('CRM/Contacts/Edit', [
            'contact' => new ContactResource($contact),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $this->contactService->update($contact, $request->validated());

        return redirect()->route('crm.contacts.show', $contact)->with('success', 'Contact mis à jour.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $this->contactService->delete($contact);

        return redirect()->route('crm.contacts.index')->with('success', 'Contact supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'sources'   => Contact::SOURCES,
        ];
    }
}
