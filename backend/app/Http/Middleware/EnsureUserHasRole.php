<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Route guard for coarse-grained access, e.g. `->middleware('role:admin,owner')`.
 * Per-record ownership checks live in the Policies, not here.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            throw new AccessDeniedHttpException(
                'This action requires one of the following roles: '.implode(', ', $roles).'.'
            );
        }

        return $next($request);
    }
}
