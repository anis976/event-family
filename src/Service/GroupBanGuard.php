<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use App\Repository\UserBanRepository;

/**
 * Règles de bannissement au sein d'un groupe (messagerie vers staff).
 *
 * Un utilisateur banni dans un groupe ne peut pas envoyer de messages
 * au propriétaire ni aux modérateurs de ce groupe.
 */
final class GroupBanGuard
{
    public function __construct(
        private readonly UserBanRepository $userBanRepository,
    ) {
    }

    public function hasActiveGroupBan(User $user, Group $group): bool
    {
        return null !== $this->userBanRepository->findActiveBanForUserInGroup($user, $group);
    }

    public function isGroupStaff(User $user, Group $group): bool
    {
        if (null !== $group->getOwner() && $group->getOwner()->getId() === $user->getId()) {
            return true;
        }

        foreach ($group->getGroupMembers() as $membership) {
            if ($membership->getUser()->getId() !== $user->getId()) {
                continue;
            }

            $role = $membership->getRole();

            if (GroupMemberRole::Owner === $role || GroupMemberRole::Moderator === $role) {
                return true;
            }
        }

        return false;
    }

    /**
     * À utiliser avant l'envoi d'un message (système messages à venir).
     */
    public function canSendMessageToUserInGroup(User $sender, User $recipient, Group $group): bool
    {
        if (!$this->hasActiveGroupBan($sender, $group)) {
            return true;
        }

        if (!$this->isGroupStaff($recipient, $group)) {
            return true;
        }

        return false;
    }
}
