<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Annule une inscription Google non finalisée (suppression du compte créé à la volée).
 */
final class GoogleOAuthRegistrationCancelService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function canCancel(User $user): bool
    {
        return $user->hasGoogleAccount() && !$user->isOAuthRegistrationComplete();
    }

    public function cancel(User $user): void
    {
        if (!$this->canCancel($user)) {
            throw new \LogicException('Ce compte Google ne peut pas être annulé depuis cet écran.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
