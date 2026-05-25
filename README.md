# ceedcv-maya/shared-http-laravel

Reusable HTTP utilities for Laravel APIs: standardized JSON response envelope, health endpoint, app metadata controller, exception handler.

Part of the [ceedcv-maya/maya_platform](https://github.com/Maya-AQSS/maya_platform) mono-repo. Distributed independently for reuse outside the Maya ecosystem.

## Installation

```bash
composer require ceedcv-maya/shared-http-laravel
```

```php
use Maya\Http\ResponseEnvelope;

return ResponseEnvelope::ok(['user' => $user]);
return ResponseEnvelope::error('not_found', 'User not found', status: 404);
```


## TypeScript / build notes
PSR-4 autoload from `src/`. Service providers are registered via Laravel package discovery (no manual provider registration needed).

## License

MIT — see [LICENSE](LICENSE).

## Reporting issues

The canonical source lives in [Maya-AQSS/maya_platform](https://github.com/Maya-AQSS/maya_platform). File issues there; this read-only split repo is only the published artifact.
