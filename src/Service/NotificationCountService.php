<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRequestRepository;
use App\Service\MessageService;

final class NotificationCountService
{
    public function __construct(
        private readonly GroupRequestRepository $groupRequestRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly MessageService $messageService,
    ) {
    }

    public function getMessageCount(User $user): int
    {
        $counts = $this->messageService->getUnreadCounts($user);

        return $counts['private'] + $counts['group'];
    }

    public function getInvitationCount(User $user): int
    {
        $received = $this->groupRequestRepository->countUnreadInvitationsForUser($user);
        $staffGroupIds = $this->groupMemberRepository->findStaffGroupIdsForUser($user);
        $staffPending = $this->groupRequestRepository->countUnreadPendingForGroups($staffGroupIds);

        return $received + $staffPending;
    }

    public function getTotalCount(User $user): int
    {
        return $this->getInvitationCount($user) + $this->getMessageCount($user);
    }

    public function isStaffAnywhere(User $user): bool
    {
        return $this->groupMemberRepository->isStaffInAnyGroup($user);
    }

    /**
     * Cible prioritaire pour la cloche : invitations si non lues, sinon messages, sinon hub messages.
     */
    public function resolveBellTargetRoute(User $user): string
    {
        $counts = $this->getCounts($user);

        if ($counts['invitations'] > 0) {
            return 'app_invitations_index';
        }

        if ($counts['messages'] > 0) {
            return 'app_messages';
        }

        return 'app_messages';
    }

    /**
     * @return array{invitations: int, messages: int, total: int}
     */
    public function getCounts(User $user): array
    {
        $invitations = $this->getInvitationCount($user);
        $messages = $this->getMessageCount($user);

        return [
            'invitations' => $invitations,
            'messages' => $messages,
            'total' => $invitations + $messages,
        ];
    }
}
