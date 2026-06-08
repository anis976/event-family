<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class GoogleOAuthUserProvisioner
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function provision(GoogleUser $googleUser): User
    {
        $googleId = trim((string) $googleUser->getId());
        if ('' === $googleId) {
            throw new CustomUserMessageAccountStatusException('auth.google.profile_invalid');
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        if ('' === $email || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new CustomUserMessageAccountStatusException('auth.google.email_missing');
        }

        $existingByGoogle = $this->userRepository->findOneByGoogleId($googleId);
        if (null !== $existingByGoogle) {
            return $existingByGoogle;
        }

        $existingByEmail = $this->userRepository->findActiveByEmail($email);
        if (null !== $existingByEmail) {
            if ($existingByEmail->hasGoogleAccount()) {
                throw new CustomUserMessageAccountStatusException('auth.google.account_conflict');
            }

            throw new CustomUserMessageAccountStatusException('auth.google.use_password_login');
        }

        $user = new User();
        $user->setGoogleId($googleId);
        $user->setEmail($email);
        $user->setFirstName($this->normalizeName((string) ($googleUser->getFirstName() ?? '')));
        $user->setLastName($this->normalizeName((string) ($googleUser->getLastName() ?? '')));
        $user->setIsVerified(true);
        // Toujours passer par /inscription/google/terminer (CGU + profil si besoin).
        $user->setOAuthRegistrationComplete(false);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        return mb_strlen($name) > 100 ? mb_substr($name, 0, 100) : $name;
    }
}
