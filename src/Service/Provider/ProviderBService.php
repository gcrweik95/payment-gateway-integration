<?php

namespace App\Service\Provider;

class ProviderBService implements PaymentProviderInterface
{

    /**
     * Authorize a payment.
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     */
    public function authorize(array $paymentData): array
    {
        return [
            'status' => 'authorized',
            'provider' => 'ProviderB',
            'auth_token' => 'pb' . uniqid(),
            'timestamp' => time(),
        ];
    }

    /**
     * Capture a payment.
     *
     * @param array<string, mixed> $authData
     * @return array<string, mixed>
     */
    public function capture(array $authData): array
    {
        $captureResponse = [
            'status' => 'failed',
            'provider' => 'ProviderB',
            'message' => 'Missing authorization token for capture',
            'transaction_id' => null,
            'timestamp' => time()
        ];

        if (!isset($authData['auth_token'])) {
            return $captureResponse;
        }

        // Simulating failure scenarios (10% chance of failure)
        if (rand(1, 10) === 1) {
            $captureResponse['message'] = 'Capture system error, please try again later';
            return $captureResponse;
        }

        $captureResponse['status'] = 'captured';
        $captureResponse['transaction_id'] = 'txb' . uniqid();
        $captureResponse['message'] = null;

        return $captureResponse;
    }

    /**
     * Refund a transaction.
     *
     * @param array<string, mixed> $transactionData
     * @return array<string, mixed>
     */
    public function refund(array $transactionData): array
    {
        $refundResponse = [
            'status' => 'failed',
            'provider' => 'ProviderB',
            'refund_id' => null,
            'message' => 'Refund failed due to an unknown issue',
            'timestamp' => time()
        ];

        if (!isset($transactionData['transaction_id'])) {
            $refundResponse['message'] = 'Invalid transaction ID for refund';
            return $refundResponse;
        }

        // Simulating failure scenarios (10% chance of failure)
        if (rand(1, 10) === 1) {
            $refundResponse['message'] = 'Capture system error, please try again later';
        }

        $refundResponse['status'] = 'refunded';
        $refundResponse['refund_id'] = 'rfb' . uniqid();
        $refundResponse['message'] = 'Refund successful';

        return $refundResponse;
    }
}
