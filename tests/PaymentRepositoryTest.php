<?php

namespace App\Tests;

use App\Repository\PaymentRepository;
use PHPUnit\Framework\TestCase;
use Redis;

class PaymentRepositoryTest extends TestCase
{
    private PaymentRepository $paymentRepository;
    private Redis $redisMock;

    protected function setUp(): void
    {
        $this->redisMock = $this->createMock(Redis::class);

        $this->paymentRepository = new PaymentRepository();

        $reflection = new \ReflectionClass($this->paymentRepository);
        $redisProperty = $reflection->getProperty('redis');
        $redisProperty->setAccessible(true);
        $redisProperty->setValue($this->paymentRepository, $this->redisMock);
    }

    public function testSaveTransaction()
    {
        $transactionId = 'test_txn_123';
        $data = ['status' => 'approved', 'amount' => 100];

        $this->redisMock->expects($this->once())
            ->method('setex')
            ->with($transactionId, 3600, json_encode($data));

        $this->paymentRepository->saveTransaction($transactionId, $data);
    }

    public function testGetTransaction()
    {
        $transactionId = 'test_txn_123';
        $data = ['status' => 'approved', 'amount' => 100];

        $this->redisMock->method('get')
            ->with($transactionId)
            ->willReturn(json_encode($data));

        $result = $this->paymentRepository->getTransaction($transactionId);

        $this->assertEquals($data, $result);
    }

    public function testTransactionExists()
    {
        $transactionId = 'test_txn_123';

        $this->redisMock->method('exists')
            ->with($transactionId)
            ->willReturn(1);

        $this->assertTrue($this->paymentRepository->transactionExists($transactionId));
    }

    public function testDeleteTransaction()
    {
        $transactionId = 'test_txn_123';

        $this->redisMock->expects($this->once())
            ->method('del')
            ->with($transactionId);

        $this->paymentRepository->deleteTransaction($transactionId);
    }
}
