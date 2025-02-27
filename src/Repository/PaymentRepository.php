<?php

namespace App\Repository;

use Psr\Log\LoggerInterface;
use Redis;

class PaymentRepository
{
    private Redis $redis;
    private LoggerInterface $operationalLogger;

    public function __construct(
        #[Autowire(service: 'monolog.logger.operational')] LoggerInterface $operationalLogger
    ) {
        $this->redis = new Redis();
        $this->redis->connect($_ENV['REDIS_HOST'], 6379);
        $this->operationalLogger = $operationalLogger;
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

        $this->operationalLogger->info("Operation saved in Redis", ['operation_key' => $operationKey, 'data' => $data]);
    }

    /**
     * Retrieve operation details from Redis.
     * @param string $operationKey Combined key format: "{operationType}_{operationId}"
     * @return array<string, mixed>|null
     */
    public function getOperation(string $operationKey): ?array
    {
        $data = $this->redis->get($operationKey);
        if (!$data) {
            $this->operationalLogger->warning("Operation not found in Redis", ['operation_key' => $operationKey]);
            return null;
        }

        $this->operationalLogger->info("Operation retrieved from Redis", ['operation_key' => $operationKey]);
        return json_decode($data, true);
    }
}
