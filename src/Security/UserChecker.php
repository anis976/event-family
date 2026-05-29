<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Contrôles à la connexion (compte global).
 *
 * Le bannissement par groupe (UserBan) est géré par GroupBanGuard pour la messagerie.
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (null !== $user->getDeletedAt()) {
            throw new CustomUserMessageAccountStatusException(
                'Ce compte n\'existe plus ou a été supprimé.',
            );
        }

        if ($user->isBanned()) {
            throw new CustomUserMessageAccountStatusException(
                'Accès refusé. Ton compte a été suspendu suite à des manquements répétés.',
            );
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Ton compte n\'est pas encore activé. Ouvre le lien reçu par e-mail pour confirmer ton adresse.',
            );
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
