<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\MergeContactRequest;
use App\Http\Requests\CRM\StoreContactRequest;
use App\Http\Requests\CRM\UpdateContactRequest;
use App\Http\Resources\CRM\ContactResource;
use App\Models\CRM\Contact;
use App\Services\CRM\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->with(['customer', 'supplier'])
            ->when($request->input('search'), function ($query, $search) {
                $like = '%' . mb_strtolower($search) . '%';
                $query->where(function ($query) use ($like) {
                    $query->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(organization) LIKE ?', [$like]);
                });
            })
            ->when($request->input('customer_id'), fn ($query, $id) => $query->where('customer_id', $id))
            ->when($request->input('supplier_id'), fn ($query, $id) => $query->where('supplier_id', $id))
            ->when($request->input('source'), fn ($query, $source) => $query->where('source', $source))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->input('per_page', 15));

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $contact = $this->contactService->create($request->validated());

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Contact $contact): ContactResource
    {
        $this->authorize('view', $contact);

        $contact->load(['customer', 'supplier']);

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $this->authorize('update', $contact);

        $contact = $this->contactService->update($contact, $request->validated());

        return new ContactResource($contact);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        $this->contactService->delete($contact);

        return response()->json(null, 204);
    }

    public function search(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']]);

        $contacts = $this->contactService->search(
            $request->string('q')->toString(),
            (int) $request->input('limit', 20)
        );

        return ContactResource::collection($contacts);
    }

    public function merge(MergeContactRequest $request, Contact $contact): ContactResource
    {
        $this->authorize('merge', $contact);

        $contact = $this->contactService->merge($contact, $request->validated('contact_ids'));

        return new ContactResource($contact);
    }
}
