<?php

namespace App\Helper;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use App\Exception\InvalidPaymentException;

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
    public function validatePaymentAuthorization(array $data): void
    {
        $constraints = new Assert\Collection(['card_number' => [
                new Assert\NotBlank([
                    'message' => 'Card number should not be blank.',
                ]),
                new Assert\NotNull([
                    'message' => 'Card number should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => 'Card number must be a string.',
                ]),
                new Assert\Length([
                    'min' => 13,
                    'max' => 19,
                    'minMessage' => 'Card number must be at least {{ limit }} characters long.',
                    'maxMessage' => 'Card number cannot be longer than {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^[0-9]+$/',
                    'message' => 'Card number must contain only digits.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^(3|4|5|6)/',
                    'message' => 'Card number must start with 3 4 5 or 6.',
                ]),
            ],
            'expiry_date' => [
                new Assert\NotBlank([
                    'message' => 'Expiry date should not be blank.',
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => 'Expiry date must be a string.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^(0[1-9]|1[0-2])\/\d{2}$/',
                    'message' => 'Expiry date must be in the format MM/YY.',
                ]),
                new Assert\Callback(function ($object, $context) {
                    $currentYear = (int) date('y');
                    $currentMonth = (int) date('m');

                    [$month, $year] = explode('/', $object);
                    $month = (int) $month;
                    $year = (int) $year;

                    $expiryDate = \DateTime::createFromFormat('my', sprintf('%02d%02d', $month, $year));
                    $currentDate = new \DateTime();
                    $currentDate->modify('+6 months');

                    if ($expiryDate < $currentDate) {
                        $context->buildViolation('The expiry date must be at least 6 months in the future.')
                            ->addViolation();
                    }
                })
            ],
            'cvv' => [
                new Assert\NotBlank([
                    'message' => 'CVV should not be blank.',
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => 'CVV must be a string.',
                ]),
                new Assert\Length([
                    'min' => 3,
                    'max' => 4,
                    'minMessage' => 'CVV must be at least {{ limit }} characters long.',
                    'maxMessage' => 'CVV cannot be longer than {{ limit }} characters.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^\d{3,4}$/',
                    'message' => 'CVV must contain only digits and be 3 or 4 characters long.',
                ]),
            ],
            'amount' => [
                new Assert\NotBlank([
                    'message' => 'Amount should not be blank.',
                ]),
                new Assert\Type([
                    'type' => 'numeric',
                    'message' => 'Amount must be numeric.',
                ]),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $violationMessages = [];
            foreach ($violations as $violation) {
                $violationMessages[] = $violation->getMessage();
            }

            throw new InvalidPaymentException(implode(', ', $violationMessages));
        }
    }

    /**
     * Validate payment capture input.
     *
     * @param array<string, mixed> $data
     * @return ConstraintViolationListInterface
     */
    public function validatePaymentCapture(array $data): void
    {
        $constraints = new Assert\Collection(['auth_token' => [
                new Assert\NotBlank(['message' => 'Authorization token should not be blank.']),
                new Assert\Type(['type' => 'string', 'message' => 'Authorization token must be a string.']),
            ],
            'amount' => [
                new Assert\NotBlank(['message' => 'Amount should not be blank.']),
                new Assert\Type(['type' => 'numeric', 'message' => 'Amount must be numeric.']),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $violationMessages = [];
            foreach ($violations as $violation) {
                $violationMessages[] = $violation->getMessage();
            }

            throw new InvalidPaymentException(implode(', ', $violationMessages));
        }
    }

    /**
     * Validate payment refund input.
     *
     * @param array<string, mixed> $data
     * @return ConstraintViolationListInterface
     */
    public function validatePaymentRefund(array $data): void
    {
        $constraints = new Assert\Collection([
            'transaction_id' => [
                new Assert\NotBlank(['message' => 'Transaction Id should not be blank.']),
                new Assert\Type(['type' => 'string', 'message' => 'Transaction Id must be a string.']),
            ],
            'amount' => [
                new Assert\NotBlank(['message' => 'Amount should not be blank.']),
                new Assert\Type(['type' => 'numeric', 'message' => 'Amount must be numeric.']),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $violationMessages = [];
            foreach ($violations as $violation) {
                $violationMessages[] = $violation->getMessage();
            }

            throw new InvalidPaymentException(implode(', ', $violationMessages));
        }
    }
}
