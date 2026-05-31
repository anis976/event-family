<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserAccountSoftDeleteService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserAvatarService $userAvatarService,
    ) {
    }

    public function softDelete(User $user): string
    {
        if (null !== $user->getDeletedAt()) {
            return $user->getEmail();
        }

        $originalEmail = $user->getEmail();

        $this->userAvatarService->deleteAvatar($user);

        $user->setDeletedAt(ParisClock::now());
        $user->setEmail(sprintf(
            'deleted_%d_%s@deleted.invalid',
            $user->getId() ?? 0,
            bin2hex(random_bytes(6)),
        ));
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setPseudo(null);
        $user->setFirstName('Compte');
        $user->setLastName(sprintf('Supprimé_%d', $user->getId() ?? 0));
        $user->setIsVerified(false);
        $user->setIsBanned(false);
        $user->clearPasswordReset();
        $user->clearPendingPasswordChange();
        $user->clearAccountDeletion();

        $this->entityManager->flush();

        return $originalEmail;
    }
}
