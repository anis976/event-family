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
    public const string DENIAL_SELF = 'ui.profile.message_denial.self';
    public const string DENIAL_BANNED_IN_GROUP = 'ui.profile.message_denial.banned_in_group';

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
            return self::DENIAL_SELF;
        }

        foreach ($this->userBanRepository->findActiveBansForUser($sender) as $ban) {
            $group = $ban->getRelatedGroup();
            if (null === $group) {
                continue;
            }

            if (!$this->groupBanGuard->canSendMessageToUserInGroup($sender, $recipient, $group)) {
                return self::DENIAL_BANNED_IN_GROUP;
            }
        }

        return null;
    }
}
