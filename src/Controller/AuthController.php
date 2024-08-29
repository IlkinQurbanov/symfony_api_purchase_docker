<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController extends AbstractController
{
    private $passwordHasher;
    private $userProvider;
    private $parameterBag;

    public function __construct(UserPasswordHasherInterface $passwordHasher, UserProviderInterface $userProvider, ParameterBagInterface $parameterBag)
    {
        $this->passwordHasher = $passwordHasher;
        $this->userProvider = $userProvider;
        $this->parameterBag = $parameterBag;
    }

    #[Route('/api/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return new JsonResponse(['error' => 'Email and password are required.'], 400);
        }

        $user = $this->userProvider->loadUserByIdentifier($email);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials.'], 401);
        }

        $token = $this->generateToken($user);

        return new JsonResponse(['token' => $token], 200);
    }

    private function generateToken(User $user): string
    {
        $jwtSecret = $this->parameterBag->get('jwt_secret'); // Use parameter bag for secret
        $issuedAt = new \DateTimeImmutable();
        $expiration = $issuedAt->modify('+1 hour')->getTimestamp(); // Token valid for 1 hour
    
        $payload = [
            'iat' => $issuedAt->getTimestamp(),   // Issued at
            'exp' => $expiration,                  // Expiration time
            'email' => $user->getEmail(),          // User identifier (e.g., email)
        ];
    
        return JWT::encode($payload, $jwtSecret, 'HS256');
    }
}
