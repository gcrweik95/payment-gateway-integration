<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private LoggerInterface $operationalLogger;

    public function __construct(
        #[Autowire(service: 'monolog.logger.operational')] LoggerInterface $operationalLogger
    ) {
        $this->operationalLogger = $operationalLogger;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get('Authorization');
        if (null === $apiToken) {
            $this->operationalLogger->warning("Authentication failed: No API key provided.");
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }
        // Extract API Key from Authorization header
        $apiKey = str_replace('Bearer ', '', $request->headers->get('Authorization', ''));

        // Compare with the secret key stored in .env
        if ($apiKey !== $_ENV['API_SECRET_KEY']) {
            $this->operationalLogger->error("Authentication failed: Invalid API key", ['api_key' => $apiKey]);
            throw new AuthenticationException('Invalid API key.');
        }

        $this->operationalLogger->info("Authentication successful", ['merchant' => 'Vestiaire Collective']);

        // Create an authenticated user with ROLE_MERCHANT
        return new SelfValidatingPassport(
            new UserBadge('Vestiaire Collective', function () {
                return new InMemoryUser('Vestiaire Collective', null, ['ROLE_MERCHANT']);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized: Invalid Merchant API Key'], Response::HTTP_UNAUTHORIZED);
    }
}
