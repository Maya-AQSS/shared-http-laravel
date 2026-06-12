<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Maya\Http\Exceptions\JsonExceptionRenderer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Register the renderer and define routes that throw each exception type.
 * Uses the Testbench application; routes are defined inline per test via
 * `$this->withoutExceptionHandling()` is NOT used — we want the renderer to fire.
 */
beforeEach(function (): void {
    // Register the renderer on the testbench exception handler
    $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    // Register the renderer on the Exceptions configurator equivalent
    // In Testbench we hook into the exception handler directly
    $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

    JsonExceptionRenderer::register(
        new class($handler) {
            public function __construct(private readonly mixed $handler) {}

            public function render(callable $cb): void
            {
                $this->handler->renderable($cb);
            }
        }
    );
});

// Helper: define a route under api/* that throws the given exception
function apiRoute(string $path, \Closure $thrower): void
{
    Route::get('/api/v1/'.$path, $thrower);
}

// ─── 422 ValidationException ─────────────────────────────────────────────────

it('returns 422 with errors for ValidationException', function (): void {
    Route::get('/api/v1/test-validation', function () {
        throw ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);
    });

    $response = $this->getJson('/api/v1/test-validation');

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

// ─── 401 AuthenticationException ─────────────────────────────────────────────

it('returns 401 for AuthenticationException', function (): void {
    Route::get('/api/v1/test-auth', function () {
        throw new AuthenticationException('Unauthenticated.');
    });

    $response = $this->getJson('/api/v1/test-auth');

    $response->assertStatus(401)
        ->assertJsonStructure(['message'])
        ->assertJsonPath('message', 'Unauthenticated.');
});

// ─── 403 AuthorizationException ──────────────────────────────────────────────

it('returns 403 for AuthorizationException', function (): void {
    Route::get('/api/v1/test-forbidden', function () {
        throw new AuthorizationException('This action is unauthorized.');
    });

    $response = $this->getJson('/api/v1/test-forbidden');

    $response->assertStatus(403)
        ->assertJsonStructure(['message']);
});

// ─── 404 NotFoundHttpException ────────────────────────────────────────────────

it('returns 404 for NotFoundHttpException', function (): void {
    Route::get('/api/v1/test-notfound', function () {
        throw new NotFoundHttpException('Not found.');
    });

    $response = $this->getJson('/api/v1/test-notfound');

    $response->assertStatus(404)
        ->assertJsonStructure(['message']);
});

// ─── 405 MethodNotAllowedHttpException ───────────────────────────────────────

it('returns 405 for MethodNotAllowedHttpException', function (): void {
    Route::get('/api/v1/test-method', function () {
        throw new MethodNotAllowedHttpException(['GET'], 'Method not allowed.');
    });

    $response = $this->getJson('/api/v1/test-method');

    $response->assertStatus(405)
        ->assertJsonStructure(['message']);
});

// ─── 429 TooManyRequestsHttpException ────────────────────────────────────────

it('returns 429 for TooManyRequestsHttpException', function (): void {
    Route::get('/api/v1/test-throttle', function () {
        throw new TooManyRequestsHttpException(60, 'Too many requests.');
    });

    $response = $this->getJson('/api/v1/test-throttle');

    $response->assertStatus(429)
        ->assertJsonStructure(['message']);
});

// ─── 500 fallback ─────────────────────────────────────────────────────────────

it('returns 500 for unexpected exceptions', function (): void {
    Route::get('/api/v1/test-500', function () {
        throw new \RuntimeException('Something went wrong.');
    });

    $response = $this->getJson('/api/v1/test-500');

    $response->assertStatus(500)
        ->assertJsonStructure(['message']);
});

it('does not expose exception trace in production mode for 500', function (): void {
    $this->app['config']->set('app.debug', false);

    Route::get('/api/v1/test-500-prod', function () {
        throw new \RuntimeException('Internal secret error message');
    });

    $response = $this->getJson('/api/v1/test-500-prod');

    $response->assertStatus(500);
    $body = $response->json();
    // In production, the raw exception message must not be forwarded
    expect($body)->not->toHaveKey('trace');
    expect($body)->not->toHaveKey('exception');
    expect($body['message'])->toBe('Internal Server Error');
    expect($body['message'])->not->toContain('secret');
});

it('does not expose 5xx HttpException messages in production (e.g. 503)', function (): void {
    $this->app['config']->set('app.debug', false);

    Route::get('/api/v1/test-503-prod', function () {
        throw new \Symfony\Component\HttpKernel\Exception\HttpException(503, 'redis at 10.0.0.5 down');
    });

    $response = $this->getJson('/api/v1/test-503-prod');

    $response->assertStatus(503);
    expect($response->json()['message'])->toBe('Service Unavailable');
});

it('keeps intentional 4xx HttpException messages in production', function (): void {
    $this->app['config']->set('app.debug', false);

    Route::get('/api/v1/test-403-prod', function () {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('No puedes publicar este documento');
    });

    $response = $this->getJson('/api/v1/test-403-prod');

    $response->assertStatus(403);
    expect($response->json()['message'])->toBe('No puedes publicar este documento');
});

// ─── Non-API routes are NOT intercepted ───────────────────────────────────────

it('does not intercept non-api routes that do not expect json', function (): void {
    Route::get('/web/test', function () {
        throw new NotFoundHttpException('Not found.');
    });

    // A plain HTTP request (not expectsJson, not api/*) should not get our JSON renderer
    // The default Laravel handler will process it — we just verify no JSON override happens.
    $response = $this->get('/web/test', ['Accept' => 'text/html']);

    // The renderer should not have intercepted it — it may return HTML 404
    expect($response->status())->toBe(404);
    expect($response->headers->get('Content-Type'))->not->toContain('application/json');
});

// ─── Custom overrides ─────────────────────────────────────────────────────────

it('respects status code override for a given exception class', function (): void {
    // Test the override logic directly by calling resolveStatus via a real request+handler
    // We build a fresh wrapper that delegates to a clean Illuminate exception handler
    $freshHandler = new \Illuminate\Foundation\Exceptions\Handler($this->app);

    $wrapper = new class($freshHandler) {
        public function __construct(private readonly \Illuminate\Foundation\Exceptions\Handler $handler) {}

        public function renderable(callable $cb): void
        {
            $this->handler->renderable($cb);
        }
    };

    JsonExceptionRenderer::register($wrapper, [\RuntimeException::class => 503]);

    $request = \Illuminate\Http\Request::create('/api/v1/test-override', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $freshHandler->render($request, new \RuntimeException('Service unavailable.'));

    expect($response->getStatusCode())->toBe(503);
});

// ─── Response shape ───────────────────────────────────────────────────────────

it('response body contains message key for all exceptions', function (string $path, \Throwable $ex): void {
    Route::get('/api/v1/'.$path, fn () => throw $ex);

    $response = $this->getJson('/api/v1/'.$path);

    $response->assertJsonStructure(['message']);
})->with([
    ['ex-auth',    new AuthenticationException('Unauth')],
    ['ex-403',     new AuthorizationException('Forbidden')],
    ['ex-404',     new NotFoundHttpException('Not found')],
    ['ex-405',     new MethodNotAllowedHttpException(['GET'])],
    ['ex-429',     new TooManyRequestsHttpException(60)],
    ['ex-runtime', new \RuntimeException('Error')],
]);

it('validation response has no errors key for non-validation exceptions', function (): void {
    Route::get('/api/v1/test-no-errors', function () {
        throw new NotFoundHttpException('Not found.');
    });

    $response = $this->getJson('/api/v1/test-no-errors');

    $response->assertStatus(404);
    expect($response->json())->not->toHaveKey('errors');
});
