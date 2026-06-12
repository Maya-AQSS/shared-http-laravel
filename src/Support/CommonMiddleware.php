<?php

declare(strict_types=1);

namespace Maya\Http\Support;

use Illuminate\Http\Middleware\HandleCors;

/**
 * Registers the middleware configuration that is common across all Maya API services.
 *
 * ## Usage
 * ```php
 * // bootstrap/app.php
 * ->withMiddleware(function (Middleware $middleware): void {
 *     CommonMiddleware::register($middleware, [
 *         'jwt'        => \Maya\Auth\Middleware\JwtMiddleware::class,
 *         'permission' => \Maya\Auth\Middleware\RequirePermissionMiddleware::class,
 *     ]);
 * })
 * ```
 *
 * ## What it registers (always)
 * - `HandleCors::class` prepended to the `api` middleware group.
 * - `trustProxies(at: '*')` — required behind Traefik/load-balancers (opt-out via `'trustProxies' => false`).
 *
 * ## Options
 * | Key                 | Type       | Default | Description                                       |
 * |---------------------|------------|---------|---------------------------------------------------|
 * | `trustProxies`      | bool       | `true`  | Whether to call `trustProxies(at: '*')`.          |
 * | `trimStringsExcept` | string[]   | `[]`    | If non-empty, calls `trimStrings(except: [...])`. |
 * | `apiPrepend`        | class-string[] | `[]` | Extra middleware prepended after HandleCors.    |
 *
 * ## Source provenance
 * Pattern extracted from the five Maya backend `bootstrap/app.php` files:
 *
 * | Service       | CORS prepend | trustProxies | trimStrings except     | Extra API prepend            |
 * |---------------|:---:|:---:|------------------------|------------------------------|
 * | maya_dms      |  ✓  |  —  | content.*, description.*, default_content.* | —         |
 * | maya_dashboard|  ✓  |  —  | —                      | —                            |
 * | maya_authorization | ✓ | — | —                     | —                            |
 * | maya_audit    |  ✓  |  ✓  | —                      | —                            |
 * | maya_logs     |  ✓  |  ✓  | —                      | SetLocaleFromAcceptLanguage  |
 *
 * Only `HandleCors` + `trustProxies` appear in 2+ services and are genuinely universal.
 * The rest are service-specific and must be passed as parameters.
 */
final class CommonMiddleware
{
    /**
     * Register common middleware on the given configurator.
     *
     * @param  object                $middleware  The Middleware configurator (Illuminate\Foundation\Configuration\Middleware or compatible).
     * @param  array<string, class-string> $aliases  Alias → FQCN map for `$middleware->alias()`.
     * @param  array<string, mixed>  $options    See class docblock for supported keys.
     */
    public static function register(object $middleware, array $aliases = [], array $options = []): void
    {
        $trustProxies     = $options['trustProxies']     ?? true;
        $trimStringsExcept = $options['trimStringsExcept'] ?? [];
        $apiPrepend       = $options['apiPrepend']       ?? [];

        // 1. Trust proxies (Traefik / load-balancers)
        if ($trustProxies !== false) {
            $middleware->trustProxies(at: '*');
        }

        // 2. CORS must be first in the API group (before auth/throttle middleware)
        $prepend = array_merge([HandleCors::class], $apiPrepend);
        $middleware->api(prepend: $prepend);

        // 3. Alias registration (service-specific)
        if ($aliases !== []) {
            $middleware->alias($aliases);
        }

        // 4. TrimStrings exclusions (service-specific rich-content fields)
        if ($trimStringsExcept !== []) {
            $middleware->trimStrings(except: $trimStringsExcept);
        }
    }
}
