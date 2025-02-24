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
     * Store transaction data in Redis
     */
    public function saveTransaction(string $transactionId, array $data, int $ttl = 3600): void
    {
        $this->redis->setex($transactionId, $ttl, json_encode($data));
    }

    /**
     * Retrieve transaction details from Redis
     */
    public function getTransaction(string $transactionId): ?array
    {
        $data = $this->redis->get($transactionId);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Check if a transaction already exists (for idempotency)
     */
    public function transactionExists(string $transactionId): bool
    {
        return $this->redis->exists($transactionId) > 0;
    }

    /**
     * Delete a transaction record
     */
    public function deleteTransaction(string $transactionId): void
    {
        $this->redis->del($transactionId);
    }
}
