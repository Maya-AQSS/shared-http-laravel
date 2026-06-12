<?php

declare(strict_types=1);

namespace Maya\Http\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Registers a uniform JSON error renderer for `api/*` routes.
 *
 * ## Usage
 * ```php
 * // bootstrap/app.php
 * ->withExceptions(function (Exceptions $exceptions): void {
 *     JsonExceptionRenderer::register($exceptions);
 * })
 * ```
 *
 * ## Exception → HTTP status map (default)
 * | Exception class                           | Status |
 * |-------------------------------------------|--------|
 * | UnauthorizedHttpException                 | 401    |
 * | AuthenticationException                   | 401    |
 * | AccessDeniedHttpException                 | 403    |
 * | AuthorizationException                    | 403    |
 * | NotFoundHttpException + ModelNotFound*    | 404    |
 * | MethodNotAllowedHttpException             | 405    |
 * | ValidationException                       | 422    |
 * | TooManyRequestsHttpException              | 429    |
 * | Any other HttpExceptionInterface          | $e->getStatusCode() |
 * | Anything else                             | 500    |
 *
 * *`ModelNotFoundException` extends `NotFoundHttpException` in Laravel 12+, but even
 * in earlier versions it ultimately produces a 404 via the default handler. This
 * renderer intercepts it via `HttpExceptionInterface` (which all Symfony HTTP
 * exceptions implement) for earlier versions.
 *
 * ## Response body
 * Always `{"message": "..."}`. Adds `{"errors": {...}}` for `ValidationException`.
 *
 * ## Production safety
 * In non-debug mode, raw 500 exception messages are replaced with a generic message
 * to avoid leaking implementation details.
 *
 * ## Custom overrides
 * The `$overrides` array maps exception class names to HTTP status codes:
 * ```php
 * JsonExceptionRenderer::register($exceptions, [
 *     \App\Exceptions\ServiceUnavailableException::class => 503,
 * ]);
 * ```
 *
 * ## Source provenance
 * Consolidated from:
 * - `maya_authorization/backend/bootstrap/app.php` lines 32-58 (main map + ValidationException errors)
 * - `maya_logs/backend/bootstrap/app.php` lines 33-69 (AuthorizationException with GateResponse code)
 *
 * The logs approach (extract `GateResponse::code()`) was intentionally NOT merged here
 * because it is specific to the authorization service's API contract (`{"error":{"code":"...",
 * "message":"..."}}`), which differs from the standard `{"message":"..."}` envelope used
 * by all other services. Apps that need the enriched AuthorizationException body should
 * add their own `renderable()` after calling this method, or pass an override.
 */
final class JsonExceptionRenderer
{
    /**
     * Register the JSON renderer on the given exception configurator.
     *
     * @param  object                 $exceptions  The exception configurator (provides `render(callable)` or `renderable(callable)`).
     * @param  array<class-string, int> $overrides  Optional map of exception class → HTTP status to override defaults.
     */
    public static function register(object $exceptions, array $overrides = []): void
    {
        $renderMethod = method_exists($exceptions, 'renderable') ? 'renderable' : 'render';

        $exceptions->{$renderMethod}(function (\Throwable $e, Request $request) use ($overrides) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null;
            }

            $status = self::resolveStatus($e, $overrides);
            $body   = self::buildBody($e, $status);

            return response()->json($body, $status);
        });
    }

    /**
     * Resolve the HTTP status code for the given throwable.
     *
     * @param  array<class-string, int> $overrides
     */
    private static function resolveStatus(\Throwable $e, array $overrides): int
    {
        // Check explicit overrides first
        foreach ($overrides as $class => $code) {
            if ($e instanceof $class) {
                return $code;
            }
        }

        return match (true) {
            $e instanceof UnauthorizedHttpException  => 401,
            $e instanceof AuthenticationException    => 401,
            $e instanceof AccessDeniedHttpException  => 403,
            $e instanceof AuthorizationException     => $e->status() ?? 403,
            $e instanceof NotFoundHttpException      => 404,
            $e instanceof MethodNotAllowedHttpException => 405,
            $e instanceof ValidationException        => 422,
            $e instanceof TooManyRequestsHttpException => 429,
            $e instanceof HttpExceptionInterface     => $e->getStatusCode(),
            default                                  => 500,
        };
    }

    /**
     * Build the JSON body for the response.
     *
     * @return array<string, mixed>
     */
    private static function buildBody(\Throwable $e, int $status): array
    {
        if ($e instanceof ValidationException) {
            return [
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ];
        }

        // In production, do not forward raw server-error messages (5xx).
        // 4xx messages are intentional (abort(403, '...'), policies) and pass through.
        if ($status >= 500 && !config('app.debug', false)) {
            return ['message' => SymfonyResponse::$statusTexts[$status] ?? 'Server Error'];
        }

        return ['message' => $e->getMessage()];
    }
}
