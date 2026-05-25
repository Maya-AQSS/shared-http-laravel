<?php

namespace Maya\Http\Health;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verifica la conectividad real con una tabla foreign-data-wrapper (FDW)
 * ejecutando una consulta trivial. Devuelve `error` si la conexión falla
 * o si la tabla referenciada no existe.
 *
 * Usado por apps que dependen de FDWs hacia maya_authorization
 * (`users_fdw`, `applications_fdw`, …).
 */
class FdwHealthCheck implements HealthCheck
{
    public function __construct(
        private readonly string $table = 'users_fdw',
        private readonly string $checkName = 'fdw',
        private readonly ?string $connection = null,
    ) {}

    public function name(): string
    {
        return $this->checkName;
    }

    public function check(): array
    {
        try {
            DB::connection($this->connection)
                ->select(sprintf('SELECT 1 FROM %s LIMIT 1', $this->table));

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
