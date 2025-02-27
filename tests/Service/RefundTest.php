<?php

namespace App\Tests\Service;

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

class RefundTest extends TestCase
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
     * ✅ Test successful payment refund
     */
    public function testRefundPaymentSuccess(): void
    {
        $transactionId = 'txa12345';
        $refundId = 'rf12345';
        $transactionData = [
            'transaction_id' => $transactionId,
            'amount' => 10000
        ];

        // Simulating an existing auth operation in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // Simulating a successful capture response from the provider
        $this->providerA->method('refund')->willReturn([
            'status' => 'refunded',
            'refund_id' => $refundId,
            'provider' => 'ProviderA',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Expect logging in order
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Refund request received', ['transaction_id' => $transactionId, 'amount' => $transactionData['amount']]],
                ['Refund successful', ['refund_id' => $refundId, 'provider' => 'ProviderA']]
            );

        // Expect Redis storage for capture operation
        $this->paymentRepository->expects($this->exactly(2))
            ->method('saveOperation')
            ->withConsecutive(
                ["refund_{$refundId}", [
                    'provider' => 'ProviderA',
                    'amount' => $transactionData['amount'],
                    'transaction_id' => $transactionId,
                    'refund_id' => $refundId,
                    'message' => 'Refunded successfully',
                    'timestamp' => time(),
                ]],
                ["refund_lookup_{$transactionId}", [
                    'refund_key' => "refund_{$refundId}"
                ]]
            );

        // Call the capture method
        $result = $this->paymentService->refundPayment($transactionData);

        // Assert transaction ID is returned correctly
        $this->assertEquals($refundId, $result);
    }

    public function testRefundPaymentIdempotency(): void
    {
        $transactionId = 'txa12345';
        $refundId = 'rf12345';
        $transactionData = [
            'transaction_id' => $transactionId,
            'amount' => 10000
        ];

        // Simulating existing capture lookup in Redis (idempotency)
        $this->paymentRepository->method('getOperation')->willReturnMap([
            ["refund_lookup_{$transactionId}", ['refund_key' => "refund_{$refundId}"]],
            ["refund_{$refundId}", ['refund_id' => $refundId, 'status' => 'refunded']]
        ]);

        // Expect **NO** new provider calls because idempotency check should return stored transaction
        $this->providerA->expects($this->never())->method('refund');

        // Expect logging of idempotency
        $this->logger->expects($this->atLeastOnce())
            ->method('debug')
            ->with('Refund already processed', ['refund_id' => $refundId]);

        // Call capture method twice with the same auth token
        $firstResult = $this->paymentService->refundPayment($transactionData);
        $secondResult = $this->paymentService->refundPayment($transactionData);

        // Ensure the same transaction ID is returned both times
        $this->assertEquals($refundId, $firstResult);
        $this->assertEquals($refundId, $secondResult);
    }

    /**
     * ✅ Test refund without a valid capture
     */
    public function testRefundWithoutCapture(): void
    {
        $transaction_id = 'nonexistent_transaction';
        $refundData = [
            'transaction_id' => $transaction_id,
            'amount' => 10000
        ];

        // Simulate that no capture exists in Redis
        $this->paymentRepository->method('getOperation')->willReturn(null);

        // Expect an error log
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Refund failed: Capture not found for refund', ['transaction_id' => $transaction_id]);

        // Expect a PaymentException to be thrown
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage("Refund failed: Capture not found.");

        // Call capturePayment method (should fail)
        $this->paymentService->refundPayment($refundData);
    }

    /**
     * ✅ Test that successful refunds are logged in Redis
     */
    public function testRefundSuccessIsLoggedInRedis(): void
    {
        $transactionId = 'txa12345';
        $refundId = 'rf12345';
        $refundData = [
            'transaction_id' => $transactionId,
            'amount' => 10000
        ];

        // Simulating an existing capture in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // Simulating a successful capture response from the provider
        $this->providerA->method('refund')->willReturn([
            'status' => 'refunded',
            'refund_id' => $refundId,
            'provider' => 'ProviderA',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // // Expect logging in order
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Refund request received', ['transaction_id' => $transactionId, 'amount' => $refundData['amount']]],
                ['Refund successful', ['refund_id' => $refundId, 'provider' => 'ProviderA']]
            );

        // // Expect Redis storage for refund operation
        $this->paymentRepository->expects($this->exactly(2))
            ->method('saveOperation')
            ->withConsecutive(
                ["refund_{$refundId}", [
                    'provider' => 'ProviderA',
                    'amount' => $refundData['amount'],
                    'refund_id' => $refundId,
                    'transaction_id' => $transactionId,
                    'message' => 'Refunded successfully',
                    'timestamp' => time(),
                ]],
                ["refund_lookup_{$transactionId}", [
                    'refund_key' => "refund_{$refundId}"
                ]]
            );

        // Call the refund method
        $result = $this->paymentService->refundPayment($refundData);

        // Assert refund ID is returned correctly
        $this->assertEquals($refundId, $result);
    }

    /**
     * ✅ Test that failed refunds are logged in Redis
     */
    public function testRefundFailureIsLoggedInRedis(): void
    {
        $transactionId = 'txa12345';
        $refundData = [
            'transaction_id' => $transactionId,
            'amount' => 10000
        ];

        // Simulating an existing capture in Redis
        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        // // Simulating a failed refund response from the provider
        $this->providerA->method('refund')->willReturn([
            'status' => 'failed',
            'provider' => 'ProviderA',
            'message' => 'Refund declined',
            'timestamp' => time()
        ]);

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Expect an error log
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Refund failed', ['message' => 'Refund declined']);

        // Expect Redis storage for failed refund operation
        $this->paymentRepository->expects($this->once())
            ->method('saveOperation')
            ->with($this->stringStartsWith("refund_error_"));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment refund failed: Refund declined');

        // // Call the refund method (should fail)
        $this->paymentService->refundPayment($refundData);
    }

    /**
     * ✅ Test input validation for refund request
     */
    public function testRefundInputValidation(): void
    {
        $validator = Validation::createValidator();
        $validationService = new ValidationService($validator);

        $testCases = [
            'missing_transaction_id' => [
                'input' => ['amount' => 10000],
                'expectedErrors' => 1
            ],
            'missing_amount' => [
                'input' => ['transaction_id' => 'txa12345'],
                'expectedErrors' => 1
            ],
            'invalid_amount' => [
                'input' => ['transaction_id' => 'txa12345', 'amount' => 'invalid'],
                'expectedErrors' => 1
            ],
        ];

        foreach ($testCases as $caseName => $case) {
            $violations = $validationService->validatePaymentRefund($case['input']);
            $this->assertGreaterThan(0, count($violations), "Expected validation failure for case: $caseName");
        }
    }

    /**
     * ✅ Test simulated provider failure (10% chance)
     */
    public function testRefundProviderFailure(): void
    {
        $transactionId = 'txa12345';
        $refundId = 'rf12345';
        $refundData = [
            'transaction_id' => $transactionId,
            'amount' => 10000
        ];

        $this->paymentRepository->method('getOperation')->willReturn([
            'provider' => 'ProviderA'
        ]);

        $this->providerA->method('refund')->willReturnCallback(function () use ($refundId) {
            // Simulating 10% failure rate
            return (rand(1, 10) === 1)
                ? ['status' => 'failed', 'message' => 'Provider declined the transaction']
                : ['status' => 'refunded', 'refund_id' => $refundId, 'provider' => 'ProviderA', 'timestamp' => time()];
        });

        $this->providerFactory->method('getOperationProvider')->willReturn($this->providerA);

        // Run multiple times to check failure simulation
        $failed = 0;
        $attempts = 100;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $result = $this->paymentService->refundPayment($refundData);
                $this->assertEquals($refundId, $result);
            } catch (PaymentException $e) {
                $failed++;
            }
        }

        // Expect around 10% failure rate (± reasonable margin)
        $this->assertGreaterThanOrEqual(5, $failed);
        $this->assertLessThanOrEqual(15, $failed);
    }
}
