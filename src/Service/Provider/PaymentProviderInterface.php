<?php

namespace App\Service\Provider;

interface PaymentProviderInterface
{
    /**
     * Authorize a payment.
     * 
     * @param array<string, mixed> $paymentData
     * @return array<string, mixed>
     * 
     * The authorize method processes an authorization request for a transaction.
     * The method takes an array containing payment data and returns an array with
     * the status of the authorization, the provider, an authorization token, a message and a timestamp.
     * The status can be either 'authorized' or 'failed'. If the status is 'failed', the
     * message will describe the reason for the failure.
     * 
     * The method simulates real-world authorization behavior. In this case, the authorization
     * will be successful if the card number starts with '4', otherwise it will fail.
     */
    public function authorize(array $paymentData): array;

    /**
     * Capture a payment.
     * 
     * @param array<string, mixed> $authData
     * @return array<string, mixed>
     * 
     * The capture method processes a capture request for a previously authorized transaction.
     * The method takes an array containing the authorization token and returns an array with
     * the status of the capture, the provider, a transaction ID, a message and a timestamp.
     * The status can be either 'captured' or 'failed'. If the status is 'failed', the
     * message will describe the reason for the failure.
     * 
     * The method simulates real-world capture behavior with probabilities. In 80% of
     * cases, the capture will be successful. In 10% of cases, the capture will fail due
     * to a system error. In the remaining 10% of cases, the capture will fail because
     * the capture amount exceeds the provider's limits.
     * 
     */
    public function capture(array $authData): array;

    /**
     * Refund a transaction.
     * 
     * @param array<string, mixed> $transactionData
     * @return array<string, mixed>
     * 
     * The refund method processes a refund request for a previously captured transaction.
     * The method takes an array containing the transaction ID and returns an array with
     * the status of the refund, the provider, the refund ID, a message and a timestamp.
     * The status can be either 'refunded' or 'failed'. If the status is 'failed', the
     * message will describe the reason for the failure.
     * 
     * The method simulates real-world refund behavior with probabilities. In 80% of
     * cases, the refund will be successful. In 10% of cases, the refund will fail due
     * to a system error. In the remaining 10% of cases, the refund will fail because
     * the refund amount exceeds the provider's limits.
     */
    public function refund(array $transactionData): array;
}
