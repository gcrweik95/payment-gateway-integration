<?php

namespace App\Tests\Service;

use App\Exception\InvalidPaymentException;
use App\Exception\PaymentException;
use App\Helper\ValidationService;
use App\Repository\PaymentRepository;
use App\Service\Payment\PaymentService;
use App\Service\Provider\ProviderAService;
use App\Service\Provider\ProviderBService;
use App\Service\Provider\ProviderFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validation;

class CaptureTest extends TestCase
{
    private PaymentService $paymentService;
    private $paymentRepository;
    private $providerFactory;
    private $providerA;
    private $providerB;
    private $logger;

    protected function setUp(): void
    {
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->providerFactory = $this->createMock(ProviderFactory::class);
        $this->providerA = $this->createMock(ProviderAService::class);
        $this->providerB = $this->createMock(ProviderBService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paymentService = new PaymentService(
            $this->paymentRepository,
            $this->providerFactory,
            $this->logger
        );
    }

    /**
     * ✅ Test successful payment capture
     */
    public function testCapturePaymentSuccess(): void
    {
        $authToken = 'pa12345';
        $transactionId = 'txa12345';
        $authData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        // Simulating an existing auth operation in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // Simulating a successful capture response from the provider
        $this->providerA->method('capture')->willReturn([
            'status' => 'captured',
            'transaction_id' => $transactionId,
            'provider' => 'ProviderA',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Expect logging in order
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Capture request received', ['auth_token' => $authToken, 'amount' => $authData['amount']]],
                ['Capture successful', ['transaction_id' => $transactionId, 'provider' => 'ProviderA']]
            );

        // Expect Redis storage for capture operation
        $this->paymentRepository->expects($this->exactly(2))
            ->method('saveOperation')
            ->withConsecutive(
                ["capture_{$transactionId}", [
                    'provider' => 'ProviderA',
                    'amount' => $authData['amount'],
                    'transaction_id' => $transactionId,
                    'auth_token' => $authToken,
                    'message' => 'Captured successfully',
                    'timestamp' => time(),
                ]],
                ["capture_lookup_{$authToken}", [
                    'capture_key' => "capture_{$transactionId}"
                ]]
            );

        // Call the capture method
        $result = $this->paymentService->capturePayment($authData);

        // Assert transaction ID is returned correctly
        $this->assertEquals($transactionId, $result);
    }

    public function testCapturePaymentIdempotency(): void
    {
        $authToken = 'pa12345';
        $transactionId = 'txa12345';
        $authData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        // Simulating existing capture lookup in Redis (idempotency)
        $this->paymentRepository->method('getOperation')->willReturnMap([
            ["capture_lookup_{$authToken}", ['capture_key' => "capture_{$transactionId}"]],
            ["capture_{$transactionId}", ['transaction_id' => $transactionId, 'status' => 'captured']]
        ]);

        // Expect **NO** new provider calls because idempotency check should return stored transaction
        $this->providerA->expects($this->never())->method('capture');

        // Expect logging of idempotency
        $this->logger->expects($this->atLeastOnce())
            ->method('debug')
            ->with('Capture already processed', ['transaction_id' => $transactionId]);

        // Call capture method twice with the same auth token
        $firstResult = $this->paymentService->capturePayment($authData);
        $secondResult = $this->paymentService->capturePayment($authData);

        // Ensure the same transaction ID is returned both times
        $this->assertEquals($transactionId, $firstResult);
        $this->assertEquals($transactionId, $secondResult);
    }

    /**
     * ✅ Test capture without a valid authorization
     */
    public function testCaptureWithoutAuthorization(): void
    {
        $authToken = 'nonexistent_auth';
        $captureData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        // Simulate that no authorization exists in Redis
        $this->paymentRepository->method('getOperation')->willReturn(null);

        // Expect an error log
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Capture failed: Authorization not found for capture', ['auth_token' => $authToken]);

        // Expect a PaymentException to be thrown
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage("Capture failed: Authorization not found.");

        // Call capturePayment method (should fail)
        $this->paymentService->capturePayment($captureData);
    }

    /**
     * ✅ Test that successful captures are logged in Redis
     */
    public function testCaptureSuccessIsLoggedInRedis(): void
    {
        $authToken = 'pa12345';
        $transactionId = 'txa12345';
        $captureData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        // Simulating an existing authorization in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // Simulating a successful capture response from the provider
        $this->providerA->method('capture')->willReturn([
            'status' => 'captured',
            'transaction_id' => $transactionId,
            'provider' => 'ProviderA',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Expect logging in order
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Capture request received', ['auth_token' => $authToken, 'amount' => $captureData['amount']]],
                ['Capture successful', ['transaction_id' => $transactionId, 'provider' => 'ProviderA']]
            );

        // Expect Redis storage for capture operation
        $this->paymentRepository->expects($this->exactly(2))
            ->method('saveOperation')
            ->withConsecutive(
                ["capture_{$transactionId}", [
                    'provider' => 'ProviderA',
                    'amount' => $captureData['amount'],
                    'transaction_id' => $transactionId,
                    'auth_token' => $authToken,
                    'message' => 'Captured successfully',
                    'timestamp' => time(),
                ]],
                ["capture_lookup_{$authToken}", [
                    'capture_key' => "capture_{$transactionId}"
                ]]
            );

        // Call the capture method
        $result = $this->paymentService->capturePayment($captureData);

        // Assert transaction ID is returned correctly
        $this->assertEquals($transactionId, $result);
    }

    /**
     * ✅ Test that failed captures are logged in Redis
     */
    public function testCaptureFailureIsLoggedInRedis(): void
    {
        $authToken = 'pa12345';
        $captureData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        // Simulating an existing authorization in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // Simulating a failed capture response from the provider
        $this->providerA->method('capture')->willReturn([
            'status' => 'failed',
            'provider' => 'ProviderA',
            'message' => 'Capture declined',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Expect an error log
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Capture failed', ['message' => 'Capture declined']);

        // Expect Redis storage for failed capture operation
        $this->paymentRepository->expects($this->once())
            ->method('saveOperation')
            ->with($this->stringStartsWith("capture_error_"));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment capture failed: Capture declined');

        // Call the capture method (should fail)
        $this->paymentService->capturePayment($captureData);
    }

    /**
     * ✅ Test input validation for capture request
     */
    public function testCaptureInputValidation(): void
    {
        $validator = Validation::createValidator();
        $validationService = new ValidationService($validator);

        $testCases = [
            'missing_auth_token' => [
                'input' => ['amount' => 10000],
                'expectedErrors' => 1
            ],
            'missing_amount' => [
                'input' => ['auth_token' => 'pa12345'],
                'expectedErrors' => 1
            ],
            'invalid_amount' => [
                'input' => ['auth_token' => 'pa12345', 'amount' => 'invalid'],
                'expectedErrors' => 1
            ],
        ];

        foreach ($testCases as $caseName => $case) {
            try {
                $validationService->validatePaymentCapture($case['input']);
                $this->fail("Expected InvalidPaymentException for case: {$caseName}");
            } catch (InvalidPaymentException $e) {
                $this->assertNotEmpty($e->getMessage(), "Validation message should not be empty for case: {$caseName}");
            }
        }
    }

    /**
     * ✅ Test simulated provider failure (10% chance)
     */
    public function testCaptureProviderFailure(): void
    {
        $authToken = 'pa12345';
        $transactionId = 'txa12345';
        $authData = [
            'auth_token' => $authToken,
            'amount' => 10000
        ];

        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        $this->providerA->method('capture')->willReturnCallback(function () use ($transactionId) {
            // Simulating 10% failure rate
            return (rand(1, 10) === 1)
                ? ['status' => 'failed', 'message' => 'Provider declined the transaction']
                : ['status' => 'captured', 'transaction_id' => $transactionId, 'provider' => 'ProviderA', 'timestamp' => time()];
        });

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Run multiple times to check failure simulation
        $failed = 0;
        $attempts = 100;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $this->paymentService->capturePayment($authData);
                $this->assertEquals($transactionId, $result);
            } catch (PaymentException $e) {
                $failed++;
            }
        }

        // Expect around 10% failure rate (± reasonable margin)
        $this->assertGreaterThanOrEqual(5, $failed);
        $this->assertLessThanOrEqual(15, $failed);
    }
}
