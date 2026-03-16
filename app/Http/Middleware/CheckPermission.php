<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage: Route::middleware('permission:invoices.create')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        if (!$user->current_company_id) {
            abort(403, 'No company selected.');
        }

        if (!$user->hasPermission($permission)) {
            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
