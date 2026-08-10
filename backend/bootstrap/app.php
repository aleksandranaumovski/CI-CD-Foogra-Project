<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA (cookie) auth for the React app, alongside bearer tokens.
        $middleware->statefulApi();

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Trust the Vite dev server / any reverse proxy sitting in front of the API.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Every API failure comes back in the same envelope:
         *
         *   { "message": "...", "errors": { "field": ["..."] } }
         *
         * so the frontend has one error path to handle instead of six.
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $e->getMessage(),
                    $e->errors(),
                ],
                $e instanceof AuthenticationException => [
                    Response::HTTP_UNAUTHORIZED,
                    'Unauthenticated. Please sign in to continue.',
                    null,
                ],
                $e instanceof AuthorizationException => [
                    Response::HTTP_FORBIDDEN,
                    $e->getMessage() ?: 'This action is unauthorized.',
                    null,
                ],
                $e instanceof ModelNotFoundException => [
                    Response::HTTP_NOT_FOUND,
                    'The requested resource could not be found.',
                    null,
                ],
                $e instanceof NotFoundHttpException => [
                    Response::HTTP_NOT_FOUND,
                    'The requested endpoint could not be found.',
                    null,
                ],
                $e instanceof TooManyRequestsHttpException => [
                    Response::HTTP_TOO_MANY_REQUESTS,
                    'Too many requests. Please slow down and try again shortly.',
                    null,
                ],
                $e instanceof HttpExceptionInterface => [
                    $e->getStatusCode(),
                    $e->getMessage() ?: 'Request failed.',
                    null,
                ],
                default => [
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    config('app.debug') ? $e->getMessage() : 'Something went wrong on our end.',
                    null,
                ],
            };

            $payload = ['message' => $message];

            if ($errors !== null) {
                $payload['errors'] = $errors;
            }

            if (config('app.debug') && $status === Response::HTTP_INTERNAL_SERVER_ERROR) {
                $payload['exception'] = $e::class;
                $payload['file'] = $e->getFile().':'.$e->getLine();
            }

            return response()->json($payload, $status);
        });
    })->create();
