<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

class UserProvider implements UserProviderInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Here, you should fetch the user by token, which might be the identifier in your case
        // For example:
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['apiToken' => $identifier]);
        if (!$user) {
            throw new UsernameNotFoundException(sprintf('User with token "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        // Implement this if needed
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
