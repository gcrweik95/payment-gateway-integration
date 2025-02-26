<?php

namespace App\Helper;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ValidationService
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validate payment authorization input.
     *
     * @param array<string, mixed> $data
     * @return ConstraintViolationListInterface
     */
    public function validatePaymentAuthorization(array $data): ConstraintViolationListInterface
    {
        $constraints = new Assert\Collection([
            'card_number' => [new Assert\NotBlank(), new Assert\Type('string')],
            'expiry_date' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Regex('/^(0[1-9]|1[0-2])\/\d{2}$/') // MM/YY format
            ],
            'cvv' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Length(['min' => 3, 'max' => 4]),
                new Assert\Regex('/^\d{3,4}$/')
            ],
            'amount' => [new Assert\NotBlank(), new Assert\Type('numeric')],
        ]);

        return $this->validator->validate($data, $constraints);
    }

    /**
     * Validate payment capture input.
     *
     * @param array<string, mixed> $data
     * @return ConstraintViolationListInterface
     */
    public function validatePaymentCapture(array $data): ConstraintViolationListInterface
    {
        $constraints = new Assert\Collection([
            'auth_token' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => [new Assert\NotBlank(), new Assert\Type('numeric')],
        ]);

        return $this->validator->validate($data, $constraints);
    }

    /**
     * Validate payment refund input.
     *
     * @param array<string, mixed> $data
     * @return ConstraintViolationListInterface
     */
    public function validatePaymentRefund(array $data): ConstraintViolationListInterface
    {
        $constraints = new Assert\Collection([
            'transaction_id' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => [new Assert\NotBlank(), new Assert\Type('numeric')],
        ]);

        return $this->validator->validate($data, $constraints);
    }
}
