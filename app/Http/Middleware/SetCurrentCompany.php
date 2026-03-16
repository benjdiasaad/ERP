<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-Id') ?? $user->current_company_id;

        if (!$companyId) abort(403, 'No company selected.');
        if (!$user->companies()->where('companies.id', $companyId)->exists()) {
            abort(403, 'Access denied to this company.');
        }

        if ($user->current_company_id !== (int)$companyId) {
            $user->update(['current_company_id' => $companyId]);
        }

        return $next($request);
    }
}
