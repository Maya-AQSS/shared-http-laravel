<?php

namespace Maya\Http\Health;

use Illuminate\Support\Facades\Redis;
use Throwable;

class RedisHealthCheck implements HealthCheck
{
    public function __construct(private readonly ?string $connection = null) {}

    public function name(): string
    {
        return 'redis';
    }

    public function check(): array
    {
        try {
            $pong = Redis::connection($this->connection)->ping();

            return $pong ? ['status' => 'ok'] : ['status' => 'error', 'message' => 'PING returned falsy'];
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
