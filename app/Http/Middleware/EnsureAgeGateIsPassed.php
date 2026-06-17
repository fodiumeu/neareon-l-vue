<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgeGateIsPassed
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ($user->birthdate === null || $user->age_gate_passed_at === null)) {
            return to_route('age-gate.show');
        }

        return $next($request);
    }
}
