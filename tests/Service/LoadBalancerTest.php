<?php

namespace App\Tests\Service\Payment;

use App\Repository\PaymentRepository;
use App\Service\Payment\LoadBalancerService;
use PHPUnit\Framework\TestCase;

class LoadBalancerTest extends TestCase
{
    private LoadBalancerService $loadBalancerService;
    private $paymentRepository;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->loadBalancerService = new LoadBalancerService($this->paymentRepository);
    }

    /**
     * ✅ Test load balancing distribution (60% Provider A, 40% Provider B)
     */
    public function testLoadBalancerDistribution(): void
    {
        $providerCounts = ['ProviderA' => 0, 'ProviderB' => 0];
        $totalRequests = 1000;

        // Simulate Redis counter
        $counter = 0;
        $this->paymentRepository->method('getOperation')->willReturnCallback(function () use (&$counter) {
            return ['counter' => $counter];
        });

        $this->paymentRepository->method('saveOperation')->willReturnCallback(function ($key, $data) use (&$counter) {
            if ($key === "load_balancer_counter") {
                $counter = $data['counter']; // Update counter in simulation
            }
        });

        // Simulate 1000 authorization requests
        for ($i = 0; $i < $totalRequests; $i++) {
            $provider = $this->loadBalancerService->selectProvider();
            $this->assertContains($provider, ['ProviderA', 'ProviderB']);
            $providerCounts[$provider]++;
        }

        // Check if Provider A gets around 600 and Provider B around 400 (± 5% margin)
        $this->assertGreaterThanOrEqual(570, $providerCounts['ProviderA']);
        $this->assertLessThanOrEqual(630, $providerCounts['ProviderA']);
        $this->assertGreaterThanOrEqual(370, $providerCounts['ProviderB']);
        $this->assertLessThanOrEqual(430, $providerCounts['ProviderB']);

        // Print the actual distribution for debugging
        fwrite(STDOUT, "\nProvider A: {$providerCounts['ProviderA']} times\nProvider B: {$providerCounts['ProviderB']} times\n");
    }
}
