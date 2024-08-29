<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private $userProvider;
    private $jwtSecret;

    public function __construct(UserProviderInterface $userProvider, string $jwtSecret)
    {
        $this->userProvider = $userProvider;
        $this->jwtSecret = $jwtSecret;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if (strpos($authHeader, 'Bearer ') !== 0) {
            throw new AuthenticationException('Invalid Authorization header format');
        }

        $token = substr($authHeader, 7);

        try {
            // Decode the JWT token
            $decodedToken = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            // Ensure that the decoded token contains the expected fields
            if (!isset($decodedToken->email)) {
                throw new AuthenticationException('Token does not contain email');
            }

            return new SelfValidatingPassport(
                new UserBadge($decodedToken->email, function ($userIdentifier) {
                    return $this->userProvider->loadUserByIdentifier($userIdentifier);
                })
            );

        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid API token: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?JsonResponse
    {
        // Handle successful authentication
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse(['error' => $exception->getMessage()], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
