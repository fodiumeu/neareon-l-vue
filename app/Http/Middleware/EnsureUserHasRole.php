<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $requiredRole = UserRole::tryFrom($role);

        if (! $requiredRole || ! $request->user()?->hasAtLeastRole($requiredRole)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
