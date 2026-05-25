<?php

namespace Maya\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Maya\Http\Health\HealthCheck;

/**
 * Controlador base con tres endpoints estándar:
 *  - GET /health        (alias `index()`): estado completo de todos los checks
 *  - GET /health/live   (alias `live()`):  liveness — siempre 200, no toca dependencias
 *  - GET /health/ready  (alias `ready()`): readiness — agrega checks marcados como críticos
 *
 * Las apps deben extender este controlador y declarar:
 *
 *   protected function checks(): array {
 *       return [new \Maya\Http\Health\DatabaseHealthCheck(), new \Maya\Http\Health\RedisHealthCheck()];
 *   }
 *
 *   // Opcional: subset usado en /ready (por defecto = checks())
 *   protected function readinessChecks(): array { ... }
 */
abstract class AbstractHealthCheckController extends Controller
{
    /**
     * Lista de checks completos (/health).
     *
     * @return array<int, HealthCheck>
     */
    abstract protected function checks(): array;

    /**
     * Lista de checks usados en /ready. Por defecto, los mismos que /health.
     *
     * @return array<int, HealthCheck>
     */
    protected function readinessChecks(): array
    {
        return $this->checks();
    }

    public function index(): JsonResponse
    {
        return $this->runChecks($this->checks());
    }

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        return $this->runChecks($this->readinessChecks());
    }

    /**
     * @param  array<int, HealthCheck>  $checks
     */
    private function runChecks(array $checks): JsonResponse
    {
        $results = [];
        $allOk   = true;

        foreach ($checks as $check) {
            $result = $check->check();
            $results[$check->name()] = $result;
            if (($result['status'] ?? 'error') !== 'ok') {
                $allOk = false;
            }
        }

        return response()->json(
            [
                'status' => $allOk ? 'ok' : 'degraded',
                'checks' => $results,
            ],
            $allOk ? 200 : 503,
        );
    }
}
