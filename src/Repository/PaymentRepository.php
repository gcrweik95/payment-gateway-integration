<?php

namespace App\Repository;

use Redis;

class PaymentRepository
{
    private Redis $redis;

    public function __construct()
    {
        $this->redis = new Redis();
        $this->redis->connect($_ENV['REDIS_HOST'], 6379);
    }

    /**
     * Store an operation (authorization, capture, refund) in Redis.
     * @param string $operationKey Combined key format: "{operationType}_{operationId}"
     * @param array<string, mixed> $data
     */
    public function saveOperation(string $operationKey, array $data, int $ttl = 18000): void
    {
        $data['timestamp'] = time(); // Store timestamp for logging/debugging
        $this->redis->setex($operationKey, $ttl, json_encode($data));
    }

    /**
     * Retrieve operation details from Redis.
     * @param string $operationKey Combined key format: "{operationType}_{operationId}"
     * @return array<string, mixed>|null
     */
    public function getOperation(string $operationKey): ?array
    {
        $data = $this->redis->get($operationKey);
        return $data ? json_decode($data, true) : null;
    }
}
