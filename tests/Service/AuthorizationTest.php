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

class AuthorizationTest extends TestCase
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
     * ✅ Test successful payment authorization
     */
    public function testAuthorizePaymentSuccess(): void
    {
        $paymentData = [
            'card_number' => '4111111111111111', // Provider A valid card
            'expiry_date' => '12/25',
            'cvv' => '123',
            'amount' => 10000
        ];

        $authToken = 'pa12345';

        $this->providerA->method('authorize')->willReturn([
            'status' => 'authorized',
            'auth_token' => $authToken,
            'provider' => 'ProviderA',
            'timestamp' => time(),
        ]);

        $this->providerFactory->method('createProvider')->willReturn($this->providerA);

        $this->paymentRepository
            ->expects($this->once())
            ->method('saveOperation')
            ->with("auth_{$authToken}");

        $result = $this->paymentService->authorizePayment($paymentData);
        $this->assertEquals($authToken, $result);
    }

    /**
     * ✅ Test failed authorization for Provider A (Invalid Card)
     */
    public function testAuthorizePaymentFailureProviderA(): void
    {
        $paymentData = [
            'card_number' => '5111111111111111', // Invalid for Provider A
            'expiry_date' => '12/25',
            'cvv' => '123',
            'amount' => 10000
        ];

        $this->providerA->method('authorize')->willReturn([
            'status' => 'failed',
            'auth_token' => null,
            'provider' => 'ProviderA',
            'message' => 'Authorization failed',
            'timestamp' => time(),
        ]);

        $this->providerFactory->method('createProvider')->willReturn($this->providerA);

        $this->paymentRepository
            ->expects($this->once())
            ->method('saveOperation')
            ->with($this->stringStartsWith('auth_error_'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment authorization failed: Authorization failed');

        $this->paymentService->authorizePayment($paymentData);
    }

    /**
     * ✅ Test that all authorization attempts are logged in Redis
     */
    public function testSuccessfulAuthorizationIsLoggedInRedis(): void
    {
        $paymentDataSuccess = [
            'card_number' => '4111111111111111', // Valid for Provider A
            'expiry_date' => '12/25',
            'cvv' => '123',
            'amount' => 10000
        ];

        $authToken = 'pa12345';

        // Simulating successful authorization
        $this->providerA->method('authorize')->willReturn([
            'status' => 'authorized',
            'auth_token' => $authToken,
            'provider' => 'ProviderA',
            'timestamp' => time(),
        ]);

        $this->providerFactory->method('createProvider')->willReturn($this->providerA);

        // Expect Redis to store the successful authorization
        $this->paymentRepository
            ->expects($this->once())
            ->method('saveOperation')
            ->with("auth_{$authToken}");

        $this->paymentService->authorizePayment($paymentDataSuccess);
    }

    /**
     * ✅ Test that all authorization attempts are logged in Redis
     */
    public function testFailedAuthorizationIsLoggedInRedis(): void
    {
        $paymentDataFailure = [
            'card_number' => '5111111111111111', // Invalid for Provider A
            'expiry_date' => '12/25',
            'cvv' => '123',
            'amount' => 10000
        ];

        $this->providerFactory->method('createProvider')->willReturn($this->providerA);

        // Simulating failed authorization
        $this->providerA->method('authorize')->willReturn([
            'status' => 'failed',
            'auth_token' => null,
            'provider' => 'ProviderA',
            'message' => 'Authorization failed',
            'timestamp' => time(),
        ]);

        $this->expectException(PaymentException::class);

        // Expect Redis to store the failed authorization with an error key
        $this->paymentRepository
            ->expects($this->once())
            ->method('saveOperation')
            ->with($this->stringStartsWith('auth_error_'));

        $this->paymentService->authorizePayment($paymentDataFailure);
    }

    public function testAuthorizePaymentInvalidInput(): void
    {
        $validator = Validation::createValidator();
        $validationService = new ValidationService($validator);

        $invalidPayments = [
            'missing_card_number' => [
                'expiry_date' => '12/25',
                'cvv' => '123',
                'amount' => 10000
            ],
            'invalid_card_number' => [
                'card_number' => '123',
                'expiry_date' => '12/25',
                'cvv' => '123',
                'amount' => 10000
            ],
            'missing_expiry_date' => [
                'card_number' => '4111111111111111',
                'cvv' => '123',
                'amount' => 10000
            ],
            'invalid_expiry_date_format' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '13/25', // Invalid MM
                'cvv' => '123',
                'amount' => 10000
            ],
            'expired_card' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '01/21', // Expired date
                'cvv' => '123',
                'amount' => 10000
            ],
            'missing_cvv' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'amount' => 10000
            ],
            'invalid_cvv' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'cvv' => '12a',
                'amount' => 10000
            ],
            'missing_amount' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'cvv' => '123'
            ],
            'invalid_amount' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'cvv' => '123',
                'amount' => 'not_a_number'
            ]
        ];

        foreach ($invalidPayments as $testCase => $paymentData) {
            try {
                $validationService->validatePaymentAuthorization($paymentData);
                $this->fail("Expected InvalidPaymentException for case: {$testCase}");
            } catch (InvalidPaymentException $e) {
                $this->assertNotEmpty($e->getMessage(), "Validation message should not be empty for case: {$testCase}");
            }
        }
    }
}
