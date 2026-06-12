<?php

declare(strict_types=1);

use Maya\Http\Support\CommonMiddleware;

/**
 * CommonMiddleware::register() is a configuration helper invoked inside
 * ->withMiddleware(). We test it by constructing a spy/double for the
 * Middleware configurator and asserting the correct methods are called.
 */

// ─── Minimal Middleware spy ───────────────────────────────────────────────────

/**
 * @return object A spy that records method calls on the Middleware configurator.
 */
function makeMiddlewareSpy(): object
{
    return new class {
        public array $calls = [];

        public function api(array $prepend = [], array $append = []): static
        {
            $this->calls[] = ['api', compact('prepend', 'append')];
            return $this;
        }

        public function trustProxies(string $at = '*', int $headers = 0): static
        {
            $this->calls[] = ['trustProxies', compact('at', 'headers')];
            return $this;
        }

        public function alias(array $aliases): static
        {
            $this->calls[] = ['alias', $aliases];
            return $this;
        }

        public function trimStrings(array $except = []): static
        {
            $this->calls[] = ['trimStrings', $except];
            return $this;
        }

        public function hasCall(string $method): bool
        {
            foreach ($this->calls as [$m]) {
                if ($m === $method) {
                    return true;
                }
            }
            return false;
        }

        public function getCallArgs(string $method): array
        {
            foreach ($this->calls as [$m, $args]) {
                if ($m === $method) {
                    return $args;
                }
            }
            return [];
        }
    };
}

// ─── Default registration ─────────────────────────────────────────────────────

it('registers CORS prepend on the api group by default', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy);

    expect($spy->hasCall('api'))->toBeTrue();
    $args = $spy->getCallArgs('api');
    expect($args['prepend'])->toContain(\Illuminate\Http\Middleware\HandleCors::class);
});

it('registers trustProxies by default', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy);

    expect($spy->hasCall('trustProxies'))->toBeTrue();
    $args = $spy->getCallArgs('trustProxies');
    expect($args['at'])->toBe('*');
});

// ─── Alias registration ───────────────────────────────────────────────────────

it('does not call alias when no aliases are provided', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, []);

    expect($spy->hasCall('alias'))->toBeFalse();
});

it('calls alias with provided aliases', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [
        'jwt' => \stdClass::class,
        'permission' => \stdClass::class,
    ]);

    expect($spy->hasCall('alias'))->toBeTrue();
    $args = $spy->getCallArgs('alias');
    expect($args)->toHaveKey('jwt');
    expect($args)->toHaveKey('permission');
});

// ─── TrimStrings except option ────────────────────────────────────────────────

it('does not call trimStrings when trimStringsExcept option is not provided', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [], []);

    expect($spy->hasCall('trimStrings'))->toBeFalse();
});

it('calls trimStrings when trimStringsExcept option is provided', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [], [
        'trimStringsExcept' => ['content.*', 'description.*'],
    ]);

    expect($spy->hasCall('trimStrings'))->toBeTrue();
    $args = $spy->getCallArgs('trimStrings');
    expect($args)->toContain('content.*');
    expect($args)->toContain('description.*');
});

// ─── trustProxies opt-out ─────────────────────────────────────────────────────

it('skips trustProxies when trustProxies option is false', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [], ['trustProxies' => false]);

    expect($spy->hasCall('trustProxies'))->toBeFalse();
});

it('still registers CORS even when trustProxies is disabled', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [], ['trustProxies' => false]);

    expect($spy->hasCall('api'))->toBeTrue();
});

// ─── Extra API prepend middleware ─────────────────────────────────────────────

it('appends extra middleware to the api prepend list', function (): void {
    $spy = makeMiddlewareSpy();

    CommonMiddleware::register($spy, [], [
        'apiPrepend' => [\stdClass::class],
    ]);

    $args = $spy->getCallArgs('api');
    expect($args['prepend'])->toContain(\Illuminate\Http\Middleware\HandleCors::class);
    expect($args['prepend'])->toContain(\stdClass::class);
});

// ─── Does not crash ───────────────────────────────────────────────────────────

it('does not throw when called with all default parameters', function (): void {
    $spy = makeMiddlewareSpy();

    expect(fn () => CommonMiddleware::register($spy))->not->toThrow(\Throwable::class);
});

it('does not throw with all options set', function (): void {
    $spy = makeMiddlewareSpy();

    expect(fn () => CommonMiddleware::register($spy, ['jwt' => \stdClass::class], [
        'trustProxies'     => true,
        'trimStringsExcept' => ['content.*'],
        'apiPrepend'       => [\stdClass::class],
    ]))->not->toThrow(\Throwable::class);
});
