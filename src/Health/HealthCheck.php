<?php

namespace Maya\Http\Health;

/**
 * Una verificación de salud individual (BD, Redis, RabbitMQ, etc.).
 * Implementaciones concretas viven en cada app o en `Maya\Http\Health\Checks`.
 */
interface HealthCheck
{
    /** Nombre corto identificativo (`database`, `redis`, `rabbitmq`...). */
    public function name(): string;

    /**
     * Ejecuta la verificación.
     *
     * @return array{status: 'ok'|'error', message?: string}
     */
    public function check(): array;
}
