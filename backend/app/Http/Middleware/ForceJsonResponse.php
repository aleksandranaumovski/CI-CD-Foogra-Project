<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees the API always negotiates JSON, even when a client (curl, a form
 * post, a browser address bar) forgets to send an Accept header. Without this
 * Laravel would try to redirect unauthenticated requests to a login route that
 * does not exist in an API-only application.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
