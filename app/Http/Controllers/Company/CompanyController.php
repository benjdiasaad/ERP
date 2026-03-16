<?php

declare(strict_types=1);

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\Company\CompanyResource;
use App\Models\Company\Company;
use App\Models\User;
use App\Services\Company\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companyService,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companyService->getUserCompanies(auth()->user());

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->create($request->validated());

        return (new CompanyResource($company))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $this->authorize('update', $company);

        $company = $this->companyService->update($company, $request->validated());

        return new CompanyResource($company);
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        try {
            $this->companyService->delete($company);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 204);
    }

    public function addUser(Request $request, Company $company): JsonResponse
    {
        $this->authorize('addUser', $company);

        $request->validate([
            'user_id'    => ['required', 'integer', 'exists:users,id'],
            'is_default' => ['boolean'],
        ]);

        try {
            $user = User::findOrFail($request->integer('user_id'));
            $this->companyService->addUser($company, $user, $request->boolean('is_default'));
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 200);
    }

    public function removeUser(Company $company, User $user): JsonResponse
    {
        $this->authorize('removeUser', $company);

        try {
            $this->companyService->removeUser($company, $user);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return response()->json(null, 200);
    }

    public function switch(Request $request, Company $company): JsonResponse
    {
        $this->authorize('switch', $company);

        try {
            $switched = $this->companyService->switchCompany(
                auth()->user(),
                $company->id,
            );
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }

        return (new CompanyResource($switched))->response();
    }
}
