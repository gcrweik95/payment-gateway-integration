<?php

namespace App\Controller;

use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RedisTestController extends AbstractController
{
    #[Route('/redis/test', name: 'app_redis_test', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): JsonResponse
    {
        // Test saving a transaction
        $transactionId = 'test_txn_123';
        $testData = ['status' => 'approved', 'amount' => 100];

        $paymentRepository->saveTransaction($transactionId, $testData);
        $retrievedData = $paymentRepository->getTransaction($transactionId);

        return $this->json([
            'id' => $transactionId,
            'stored' => $testData,
            'retrieved' => $retrievedData,
            'exists' => $paymentRepository->transactionExists($transactionId),
        ]);
    }
}
