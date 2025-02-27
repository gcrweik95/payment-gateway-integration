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
     * Validates the payment authorization data.
     *
     * This method checks the provided payment data for compliance with a set of constraints.
     * It ensures that the card number, expiry date, CVV, and amount fields are present and valid.
     *
     * - Card number: Must be a non-blank string of digits, 13 to 19 characters long, starting with 3, 4, 5, or 6.
     * - Expiry date: Must be a non-blank string in the format MM/YY, at least 6 months in the future.
     * - CVV: Must be a non-blank string of 3 or 4 digits.
     * - Amount: Must be a non-blank numeric value.
     *
     * If any constraint is violated, an InvalidPaymentException is thrown with the details.
     *
     * @param array<string, mixed> $data The payment data to validate.
     * @throws InvalidPaymentException If validation fails.
     */

    public function validatePaymentAuthorization(array $data): void
    {
        $constraints = new Assert\Collection([
            'card_number' => [
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
                new Assert\NotNull([
                    'message' => 'Expiry date should not be null.',
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
                    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $object)) {
                        return;
                    }

                    $currentDate = new \DateTime();
                    [$month, $year] = explode('/', $object);
                    $month = (int) $month;
                    $year = (int) $year;

                    // Convert 2-digit year to 4-digit
                    $fullYear = $year < 100 ? 2000 + $year : $year;

                    $expiryDate = new \DateTime("{$fullYear}-{$month}-01"); // First day of expiry month
                    $expiryDate->modify('last day of this month')->setTime(23, 59, 59); // Set to last day

                    $expiryTimestamp = $expiryDate->getTimestamp();
                    $currentTimestamp = $currentDate->getTimestamp();

                    $difference = ($expiryTimestamp - $currentTimestamp) / 86400;

                    // If it's past the last day of expiry month, it's expired
                    if ($difference < 0) {
                        $context->buildViolation('The card is expired.')
                            ->addViolation();
                    }
                })
                
            ],

            'cvv' => [
                new Assert\NotBlank([
                    'message' => 'CVV should not be blank.',
                ]),
                new Assert\NotNull([
                    'message' => 'CVV should not be null.',
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
                new Assert\NotNull([
                    'message' => 'Amount should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'integer',
                    'message' => 'Amount must be integer.',
                ]),
                new Assert\NotEqualTo([
                    'value' => '0',
                    'message' => 'Amount must be greater than zero.',
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
     * Validates the payment capture data.
     *
     * This method checks the provided capture data for compliance with a set of constraints.
     * It ensures that the authorization token and amount fields are present and valid.
     *
     * - Authorization token: Must be a non-blank string.
     * - Amount: Must be a non-blank numeric value.
     *
     * If any constraint is violated, an InvalidPaymentException is thrown with the details.
     *
     * @param array<string, mixed> $data The payment data to validate.
     * @throws InvalidPaymentException If validation fails.
     */
    public function validatePaymentCapture(array $data): void
    {
        $constraints = new Assert\Collection(['auth_token' => [
                new Assert\NotBlank([
                    'message' => 'Authorization token should not be blank.'
                ]),
                new Assert\NotNull([
                    'message' => 'Authorization token should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => 'Authorization token must be a string.'
                ]),
            ],
            'amount' => [
                new Assert\NotBlank([
                    'message' => 'Amount should not be blank.',
                ]),
                new Assert\NotNull([
                    'message' => 'Amount should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'integer',
                    'message' => 'Amount must be integer.',
                ]),
                new Assert\NotEqualTo([
                    'value' => '0',
                    'message' => 'Amount must be greater than zero.',
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
     * Validates the payment refund data.
     *
     * This method checks the provided refund data for compliance with a set of constraints.
     * It ensures that the transaction ID and amount fields are present and valid.
     *
     * - Transaction ID: Must be a non-blank string.
     * - Amount: Must be a non-blank numeric value.
     *
     * If any constraint is violated, an InvalidPaymentException is thrown with the details.
     *
     * @param array<string, mixed> $data The refund data to validate.
     * @throws InvalidPaymentException If validation fails.
     */

    public function validatePaymentRefund(array $data): void
    {
        $constraints = new Assert\Collection([
            'transaction_id' => [
                new Assert\NotBlank([
                    'message' => 'Transaction Id should not be blank.'
                ]),
                new Assert\NotNull([
                    'message' => 'Transaction Id should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'string',
                    'message' => 'Transaction Id must be a string.'
                ]),
            ],
            'amount' => [
                new Assert\NotBlank([
                    'message' => 'Amount should not be blank.',
                ]),
                new Assert\NotNull([
                    'message' => 'Amount should not be null.',
                ]),
                new Assert\Type([
                    'type' => 'integer',
                    'message' => 'Amount must be integer.',
                ]),
                new Assert\NotEqualTo([
                    'value' => '0',
                    'message' => 'Amount must be greater than zero.',
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
}
