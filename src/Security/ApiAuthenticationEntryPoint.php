<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class ApiAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private LoggerInterface $operationalLogger;

    public function __construct(
        #[Autowire(service: 'monolog.logger.operational')] LoggerInterface $operationalLogger
    ) {
        $this->operationalLogger = $operationalLogger;
    }

    public function start(Request $request, AuthenticationException $authException = null): JsonResponse
    {
        $this->operationalLogger->warning("Unauthorized API request received", ['request_path' => $request->getPathInfo()]);

        return new JsonResponse(
            ['error' => 'Unauthorized: Missing Merchant API key.'],
            JsonResponse::HTTP_UNAUTHORIZED
        );
    }
}
