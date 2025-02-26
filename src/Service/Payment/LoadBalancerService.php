<?php

namespace App\Service\Payment;

use App\Repository\PaymentRepository;

class LoadBalancerService
{
    private PaymentRepository $paymentRepository;
    private const LOAD_BALANCER_KEY = 'load_balancer_counter';

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Selects a payment provider based on a load balancing counter.
     *
     * The counter is stored in Redis and incremented after each call.
     * The provider is selected as follows:
     * - ProviderA: 60% of the time (counter % 10 < 6)
     * - ProviderB: 40% of the time (counter % 10 >= 6)
     *
     * @return string The name of the selected provider (ProviderA or ProviderB)
     */
    public function selectProvider(): string
    {
        // Retrieve counter from Redis, defaulting to 0 if not found
        $counterObject = $this->paymentRepository->getOperation(self::LOAD_BALANCER_KEY);
        $counter = $counterObject['counter'] ?? 0;

        // Determine provider (60% ProviderA, 40% ProviderB)
        $provider = ($counter % 10 < 6) ? 'ProviderA' : 'ProviderB';

        // Increment counter and store it back in Redis
        $this->paymentRepository->saveOperation(self::LOAD_BALANCER_KEY, ['counter' => $counter + 1], 300);

        return $provider;
    }
}
