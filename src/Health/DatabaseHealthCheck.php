<?php

namespace Maya\Http\Health;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private readonly ?string $connection = null) {}

    public function name(): string
    {
        return 'database';
    }

    public function check(): array
    {
        try {
            DB::connection($this->connection)->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
