<?php

namespace App\Controller;

use App\Helper\ValidationService;
use App\Service\Payment\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class PaymentController extends AbstractController
{
    private PaymentService $paymentService;
    private ValidationService $validationService;

    public function __construct(PaymentService $paymentService, ValidationService $validationService)
    {
        $this->paymentService = $paymentService;
        $this->validationService = $validationService;
    }

    /**
     * Handle a payment request with the given validation method and payment function.
     *
     * @param Request $request The request with the payment data.
     * @param callable $paymentFunction The payment function to call with the validated data.
     * @param string $validationMethod The name of the validation method to call in the ValidationService.
     * @param string $successKey The key to use for the success response.
     * @return JsonResponse The response.
     */
    private function handlePaymentRequest(
        Request $request,
        callable $paymentFunction,
        string $validationMethod,
        string $successKey
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate input using ValidationService
        $violations = $this->validationService->$validationMethod($data);
        if (count($violations) > 0) {
            return $this->json(['status' => 'error', 'message' => 'Invalid request data'], 400);
        }

        try {
            $result = $paymentFunction($data);

            return $this->json([
                'status' => 'success',
                $successKey => $result
            ], 200);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Authorize a payment with the given payment data.
     *
     * @param Request $request The request with the payment data.
     * @return JsonResponse The response.
     */
    #[Route('/api/payment/authorize', name: 'payment_authorize', methods: ['POST'])]
    #[OA\Post(
        path: '/api/payment/authorize',
        summary: 'Authorize a payment',
        description: 'Sends payment details to the payment provider for authorization.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['card_number', 'expiry_date', 'cvv', 'amount'],
                properties: [
                    new OA\Property(property: 'card_number', type: 'string', example: '4111111111111111'),
                    new OA\Property(property: 'expiry_date', type: 'string', example: '12/24'),
                    new OA\Property(property: 'cvv', type: 'string', example: '123'),
                    new OA\Property(property: 'amount', type: 'number', example: 1000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: '200',
                description: 'Authorization successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'auth_token', type: 'string', example: 'abcdef123456'),
                    ]
                )
            ),
            new OA\Response(
                response: '400',
                description: 'Authorization failed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid card number'),
                    ]
                )
            ),
            new OA\Response(
                response: '500',
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error'),
                    ]
                )
            )
        ]
    )]
    public function authorizePayment(Request $request): JsonResponse
    {
        return $this->handlePaymentRequest(
            $request,
            fn($data) => $this->paymentService->authorizePayment($data),
            'validatePaymentAuthorization',
            'auth_token'
        );
    }

    /**
     * Capture an authorized payment.
     *
     * Confirms the charge and moves funds from the buyer to the merchant.
     *
     * @param Request $request The request with the payment data.
     * @return JsonResponse The response.
     */
    #[Route('/api/payment/capture', name: 'payment_capture', methods: ['POST'])]
    #[OA\Post(
        path: '/api/payment/capture',
        summary: 'Capture an authorized payment',
        description: 'Confirms the charge and moves funds from the buyer to the merchant.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['auth_token', 'amount'],
                properties: [
                    new OA\Property(property: 'auth_token', type: 'string', example: 'abcdef123456'),
                    new OA\Property(property: 'amount', type: 'number', example: 1000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: '200',
                description: 'Capture successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'transactionId', type: 'string', example: 'tx123456789')
                    ]
                )
            ),
            new OA\Response(
                response: '400',
                description: 'Validation or capture error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid card number'),
                    ]
                )
            ),
            new OA\Response(
                response: '500',
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error'),
                    ]
                )
            )
        ]
    )]
    public function capturePayment(Request $request): JsonResponse
    {
        return $this->handlePaymentRequest(
            $request,
            fn($data) => $this->paymentService->capturePayment($data),
            'validatePaymentCapture',
            'transaction_id'
        );
    }

    /**
     * Refund a captured payment.
     *
     * Processes a refund request for a previously captured transaction.
     *
     * @param Request $request The request with the payment data.
     * @return JsonResponse The response.
     */
    #[Route('/api/payment/refund', name: 'payment_refund', methods: ['POST'])]
    #[OA\Post(
        path: '/api/payment/refund',
        summary: 'Refund a captured payment',
        description: 'Processes a refund request for a previously captured transaction.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['transaction_id', 'amount'],
                properties: [
                    new OA\Property(property: 'transaction_id', type: 'string', example: 'tx123456789'),
                    new OA\Property(property: 'amount', type: 'number', example: 1000)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: '200',
                description: 'Refund successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'refund_id', type: 'string', example: 'rf123456789')
                    ]
                )
            ),
            new OA\Response(
                response: '400',
                description: 'Validation or refund error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid card number'),
                    ]
                )
            ),
            new OA\Response(
                response: '500',
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Internal server error'),
                    ]
                )
            )
        ]
    )]
    public function refundPayment(Request $request): JsonResponse
    {
        return $this->handlePaymentRequest(
            $request,
            fn($data) => $this->paymentService->refundPayment($data),
            'validatePaymentRefund',
            'refund_id'
        );
    }
}
