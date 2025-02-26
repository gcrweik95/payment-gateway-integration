<?php

namespace App\Service\Payment;

use App\Exception\PaymentException;
use App\Repository\PaymentRepository;
use App\Service\Provider\ProviderFactory;

class PaymentService
{
    private PaymentRepository $paymentRepository;
    private ProviderFactory $providerFactory;

    public function __construct(PaymentRepository $paymentRepository, ProviderFactory $providerFactory)
    {
        $this->paymentRepository = $paymentRepository;
        $this->providerFactory = $providerFactory;
    }


    /**
     * Authorizes a payment with the given payment data.
     *
     * This function interacts with a payment provider to authorize a payment. 
     * On successful authorization, it stores the operation details in the repository 
     * and returns the authorization token. In case of failure, it logs the error 
     * and throws a PaymentException with an appropriate message.
     *
     * @param array<string, mixed> $paymentData The data required to authorize the payment.
     * @return string The authorization token if the payment is authorized.
     * @throws PaymentException If the payment authorization fails.
     */
    public function authorizePayment(array $paymentData): string
    {
        $provider = $this->providerFactory->createProvider();

        $authorizationResponse = $provider->authorize($paymentData);

        if ($authorizationResponse['status'] === 'authorized' && isset($authorizationResponse['auth_token'])) {
            $authToken = $authorizationResponse['auth_token'];
            $operationKey = "auth_{$authToken}";

            $this->paymentRepository->saveOperation($operationKey, [
                'provider' => $authorizationResponse['provider'],
                'amount' => $paymentData['amount'],
                'message' => $authorizationResponse['status'],
                'timestamp' => $authorizationResponse['timestamp'],
            ]);

            return $authToken;
        }

        // Process failure case
        $errorKey = "auth_error_" . uniqid();
        $this->paymentRepository->saveOperation($errorKey, [
            'provider' => $authorizationResponse['provider'] ?? 'unknown',
            'amount' => $paymentData['amount'],
            'message' => $authorizationResponse['message'] ?? 'Authorization failed',
            'timestamp' => $authorizationResponse['timestamp'],
        ]);

        throw new PaymentException('Payment authorization failed: ' . ($authorizationResponse['message'] ?? 'Unknown error'));
    }


    /**
     * Capture a payment with the given authorization data.
     *
     * This function interacts with a payment provider to capture a payment. 
     * On successful capture, it stores the operation details in the repository 
     * and returns the transaction ID. In case of failure, it logs the error 
     * and throws a PaymentException with an appropriate message.
     *
     * @param array<string, mixed> $authData The data required to capture the payment.
     * @return string The transaction ID if the payment is captured.
     * @throws PaymentException If the payment capture fails.
     */
    public function capturePayment(array $authData): string
    {
        $authToken = $authData['auth_token'];

        // Idempotency check: Ensure capture is not already processed
        $captureLookup = $this->paymentRepository->getOperation("capture_lookup_{$authToken}");
        if ($captureLookup && isset($captureLookup['capture_key'])) {
            $captureOperation = $this->paymentRepository->getOperation($captureLookup['capture_key']);
            if ($captureOperation) {
                return $captureOperation['transaction_id'];
            }
        }

        // Verify authorization exists
        $authOperation = $this->paymentRepository->getOperation("auth_{$authToken}");
        if (!$authOperation) {
            $errorKey = "capture_error_" . uniqid();
            $this->paymentRepository->saveOperation($errorKey, [
                'provider' => 'unknown',
                'amount' => $authData['amount'],
                'message' => 'Authorization not found for capture',
                'timestamp' => time(),
            ]);
            throw new PaymentException("Capture failed: Authorization not found.");
        }

        // Retrieve provider from stored auth operation
        $provider = $this->providerFactory->getOperationProvider($authOperation['provider']);

        // Capture the payment
        $captureResponse = $provider->capture($authData);

        if ($captureResponse['status'] === 'captured' && isset($captureResponse['transaction_id'])) {
            $transaction_id = $captureResponse['transaction_id'];
            $operationKey = "capture_{$transaction_id}";

            $this->paymentRepository->saveOperation($operationKey, [
                'provider' => $captureResponse['provider'],
                'amount' => $authData['amount'],
                'transaction_id' => $transaction_id,
                'auth_token' => $authToken,
                'message' => 'Captured successfully',
                'timestamp' => $captureResponse['timestamp'] ?? time(),
            ]);

            $this->paymentRepository->saveOperation("capture_lookup_{$authToken}", [
                'capture_key' => "capture_{$transaction_id}"
            ]);

            return $transaction_id;
        }

        // Process failure case
        $errorKey = "capture_error_" . uniqid();
        $this->paymentRepository->saveOperation($errorKey, [
            'provider' => $captureResponse['provider'] ?? 'unknown',
            'amount' => $authData['amount'],
            'message' => $captureResponse['message'] ?? 'Capture failed',
            'timestamp' => $captureResponse['timestamp'] ?? time(),
        ]);

        throw new PaymentException('Payment capture failed: ' . ($captureResponse['message'] ?? 'Unknown error'));
    }


    /**
     * Refunds a payment using the given transaction data.
     *
     * This function interacts with a payment provider to refund a payment. 
     * On successful refund, it stores the operation details in the repository 
     * and returns the refund id. In case of failure, it logs the error 
     * and throws a PaymentException with an appropriate message.
     *
     * @param array<string, mixed> $transactionData The data required to refund the payment.
     * @return string The refund id if the payment is refunded.
     * @throws PaymentException If the payment refund fails.
     */
    public function refundPayment(array $transactionData): string
    {
        $transactionId = $transactionData['transaction_id'];

        // Idempotency check: Ensure refund is not already processed
        $refundLookup = $this->paymentRepository->getOperation("refund_lookup_{$transactionId}");
        if ($refundLookup && isset($refundLookup['refund_key'])) {
            $refundOperation = $this->paymentRepository->getOperation($refundLookup['refund_key']);
            if ($refundOperation) {
                return $refundOperation['refund_id'];
            }
        }

        // Verify that capture exists before refunding
        $captureOperation = $this->paymentRepository->getOperation("capture_{$transactionId}");
        if (!$captureOperation) {
            $errorKey = "refund_error_" . uniqid();
            $this->paymentRepository->saveOperation($errorKey, [
                'provider' => 'unknown',
                'amount' => $transactionData['amount'],
                'message' => 'Capture not found for refund',
                'timestamp' => time(),
            ]);
            throw new PaymentException("Refund failed: Capture not found.");
        }

        // Retrieve provider from stored capture operation
        $provider = $this->providerFactory->getOperationProvider($captureOperation['provider']);

        // Capture the payment
        $refundResponse = $provider->refund($transactionData);

        if ($refundResponse['status'] === 'refunded' && isset($refundResponse['refund_id'])) {
            $refund_id = $refundResponse['refund_id'];
            $operationKey = "refund_{$refund_id}";

            $this->paymentRepository->saveOperation($operationKey, [
                'provider' => $refundResponse['provider'],
                'amount' => $transactionData['amount'],
                'refund_id' => $refund_id,
                'transaction_id' => $transactionId,
                'message' => 'Refunded successfully',
                'timestamp' => $refundResponse['timestamp'] ?? time(),
            ]);

            $this->paymentRepository->saveOperation("refund_lookup_{$transactionId}", [
                'refund_key' => "refund_{$refund_id}"
            ]);

            return $refund_id;
        }

        // Process failure case
        $errorKey = "capture_error_" . uniqid();
        $this->paymentRepository->saveOperation($errorKey, [
            'provider' => $refundResponse['provider'] ?? 'unknown',
            'amount' => $transactionData['amount'],
            'message' => $refundResponse['message'] ?? 'Capture failed',
            'timestamp' => $refundResponse['timestamp'] ?? time(),
        ]);

        throw new PaymentException('Payment capture failed: ' . ($refundResponse['message'] ?? 'Unknown error'));
    }
}
