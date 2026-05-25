<?php

namespace Maya\Http\Health;

use Throwable;

/**
 * Verifica conectividad TCP a un host:puerto via `fsockopen`. Útil para
 * dependencias externas que no tienen un driver Laravel propio
 * (RabbitMQ, WebSocket, servicios HTTP de terceros).
 *
 * El timeout por defecto (0.5s) mantiene el coste agregado bajo control
 * cuando varios `TcpHealthCheck` se ejecutan en serie.
 */
class TcpHealthCheck implements HealthCheck
{
    public function __construct(
        private readonly string $checkName,
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeout = 0.5,
    ) {}

    public function name(): string
    {
        return $this->checkName;
    }

    public function check(): array
    {
        try {
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

            if ($socket === false) {
                return [
                    'status'  => 'error',
                    'message' => sprintf('TCP %s:%d unreachable: %s', $this->host, $this->port, $errstr ?: 'timeout'),
                ];
            }

            fclose($socket);

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
