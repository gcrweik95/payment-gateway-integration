<?php

namespace App\Service\Provider;

use App\Exception\ProviderFailureException;

class ProviderAService implements PaymentProviderInterface
{
    /**
     * Authorize a payment.
     * 
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     * @throws ProviderFailureException If authorization fails.
     */
    public function authorize(array $paymentData): array
    {
        if (!str_starts_with($paymentData['card_number'], '4')) {
            throw new ProviderFailureException("Authorization failed: Invalid card number for Provider A");
        }

        return [
            'status' => 'authorized',
            'provider' => 'ProviderA',
            'auth_token' => 'pa' . uniqid(),
            'message' => null,
            'timestamp' => time()
        ];
    }


    /**
     * Capture a payment.
     * 
     * @param array<string, mixed> $authData
     * @return array<string, mixed>
     * @throws ProviderFailureException If capture fails.
     */
    public function capture(array $authData): array
    {
        if (!isset($authData['auth_token'])) {
            throw new ProviderFailureException("Capture failed: Missing authorization token.");
        }

        // Simulating failure scenarios (10% chance of failure)
        if (rand(1, 10) === 1) {
            throw new ProviderFailureException("Capture system error, please try again later.");
        }

        return [
            'status' => 'captured',
            'provider' => 'ProviderA',
            'transaction_id' => 'txa' . uniqid(),
            'message' => null,
            'timestamp' => time()
        ];
    }

    /**
     * Refund a transaction.
     * 
     * @param array<string, mixed> $transactionData
     * @return array<string, mixed>
     * @throws ProviderFailureException If refund fails.
     */
    public function refund(array $transactionData): array
    {
        if (!isset($transactionData['transaction_id'])) {
            throw new ProviderFailureException("Refund failed: Invalid transaction ID.");
        }

        // Simulating failure scenarios (10% chance of failure)
        if (rand(1, 10) === 1) {
            throw new ProviderFailureException("Refund system error, please try again later.");
        }

        return [
            'status' => 'refunded',
            'provider' => 'ProviderA',
            'refund_id' => 'rfa' . uniqid(),
            'message' => 'Refund successful',
            'timestamp' => time()
        ];
    }
}
