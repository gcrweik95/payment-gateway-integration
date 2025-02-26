<?php

namespace App\Service\Provider;

class ProviderAService implements PaymentProviderInterface
{
    /**
     * Authorize a payment.
     * 
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     */
    public function authorize(array $paymentData): array
    {
        $authResponse = [
            'status' => 'error',
            'provider' => 'ProviderA',
            'auth_token' => null,
            'message' => 'Authorization failed',
            'timestamp' => time()
        ];

        if (str_starts_with($paymentData['card_number'], '4')) {
            $authResponse['status'] = 'authorized';
            $authResponse['auth_token'] = 'pa' . uniqid();
            $authResponse['message'] = null;
        }

        return $authResponse;
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
            'provider' => 'ProviderA',
            'message' => 'Missing authorization token for capture',
            'transaction_id' => null,
            'timestamp' => time()
        ];

        if (!isset($authData['auth_token'])) {
            return $captureResponse;
        }

        // Simulating failure scenarios (10% chance of failure)
        if (rand(1, 10) === 1) {
            $captureResponse['message'] = 'Insufficient funds';
            return $captureResponse;
        }

        $captureResponse['status'] = 'captured';
        $captureResponse['transaction_id'] = 'txa' . uniqid();
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
            'provider' => 'ProviderA',
            'refund_id' => null,
            'message' => 'Refund failed due to an unknown issue',
            'timestamp' => time()
        ];

        if (!isset($transactionData['transaction_id'])) {
            $refundResponse['message'] = 'Invalid transaction ID for refund';
            return $refundResponse;
        }

        // Simulate real-world refund behavior with probabilities
        $randomChance = mt_rand(1, 10);

        if ($randomChance <= 8) {
            // ✅ 80% Success Rate
            $refundResponse['status'] = 'refunded';
            $refundResponse['refund_id'] = 'rfa' . uniqid();
            $refundResponse['message'] = 'Refund successful';
        } elseif ($randomChance === 9) {
            // ❌ 10% System Failure
            $refundResponse['message'] = 'Refund system error, please try again later';
        } else {
            // ❌ 10% Exceeding Limits
            $refundResponse['message'] = 'Refund amount exceeds provider limits';
        }

        return $refundResponse;
    }
}
