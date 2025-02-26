<?php

namespace App\Service\Provider;

use App\Service\Payment\LoadBalancerService;

class ProviderFactory
{
    private LoadBalancerService $loadBalancerService;

    public function __construct(LoadBalancerService $loadBalancerService)
    {
        $this->loadBalancerService = $loadBalancerService;
    }

    /**
     * Selects and creates a provider during authorization.
     *
     * @return PaymentProviderInterface
     */
    public function createProvider(): PaymentProviderInterface
    {
        $provider = $this->loadBalancerService->selectProvider();

        return match ($provider) {
            'ProviderA' => new ProviderAService(),
            'ProviderB' => new ProviderBService(),
            default => throw new \RuntimeException('Unknown provider selected.'),
        };
    }


    /**
     * Retrieves a provider instance for an existing operation.
     *
     * @param string $providerName Name of the provider stored in the operation.
     * @return PaymentProviderInterface
     * @throws \RuntimeException If the provider name is not recognized.
     */
    public function getOperationProvider(string $providerName): PaymentProviderInterface
    {
        return match ($providerName) {
            'ProviderA' => new ProviderAService(),
            'ProviderB' => new ProviderBService(),
            default => throw new \RuntimeException("Invalid provider stored for provider name: {$providerName}"),
        };
    }
}
