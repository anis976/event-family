<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserBanRepository;

/**
 * Règles d'envoi de messages privés directs (hors entité Message, branchée au contrôleur).
 */
final class DirectMessagePolicy
{
    public function __construct(
        private readonly UserBanRepository $userBanRepository,
        private readonly GroupBanGuard $groupBanGuard,
    ) {
    }

    public function canSendDirectMessage(User $sender, User $recipient): bool
    {
        return null === $this->getDenialReason($sender, $recipient);
    }

    public function getDenialReason(User $sender, User $recipient): ?string
    {
        if ($sender->getId() === $recipient->getId()) {
            return 'Tu ne peux pas t\'envoyer un message à toi-même.';
        }

        foreach ($this->userBanRepository->findActiveBansForUser($sender) as $ban) {
            $group = $ban->getRelatedGroup();
            if (null === $group) {
                continue;
            }

            if (!$this->groupBanGuard->canSendMessageToUserInGroup($sender, $recipient, $group)) {
                return 'Tu ne peux pas envoyer de message à un responsable ou modérateur d\'un groupe dans lequel tu es banni.';
            }
        }

        return null;
    }
}
