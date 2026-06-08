<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserBanRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Contrôles à la connexion (compte global).
 *
 * Le bannissement par groupe (UserBan) limite les interactions dans le groupe concerné.
 * Le bannissement site (isBanned) bloque la connexion jusqu'à levée par un modérateur ou admin.
 * Au 3e bannissement groupe, le compte est supprimé (soft delete) — contrôlé via deletedAt.
 */
final class UserChecker implements UserCheckerInterface
{
    private const SUSPENDED_MESSAGE = 'Accès refusé. Ton compte a été suspendu par la modération EventFamily. Consulte l\'e-mail reçu pour le motif et les démarches de recours.';

    public function __construct(
        private readonly UserBanRepository $userBanRepository,
    ) {
    }

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

        if ($this->isPlatformSuspended($user)) {
            throw new CustomUserMessageAccountStatusException(self::SUSPENDED_MESSAGE);
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

    private function isPlatformSuspended(User $user): bool
    {
        return $user->isBanned() || $this->userBanRepository->hasActivePlatformBan($user);
    }
}
