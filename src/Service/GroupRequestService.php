<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\GroupRequest;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use App\Enum\GroupRequestStatus;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRequestRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;

final class GroupRequestService
{
    public function __construct(
        private readonly GroupRequestRepository $groupRequestRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly GroupAccessService $groupAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     state: 'member'|'blocked'|'pending'|'invited'|'available',
     *     request: ?GroupRequest,
     *     refused_count: int
     * }
     */
    public function getVisitorJoinState(User $user, Group $group): array
    {
        if ($this->groupAccess->isMember($user, $group)) {
            return ['state' => 'member', 'request' => null, 'refused_count' => 0];
        }

        if ($this->groupAccess->isBannedInGroup($user, $group)) {
            return ['state' => 'blocked', 'request' => null, 'refused_count' => GroupRequestRepository::MAX_REQUESTS_AFTER_REFUSAL];
        }

        $refusedCount = $this->groupRequestRepository->countRefusedForUserAndGroup($user, $group);
        $pending = $this->groupRequestRepository->findPendingForUserAndGroup($user, $group);
        if (null !== $pending) {
            return ['state' => 'pending', 'request' => $pending, 'refused_count' => $refusedCount];
        }

        $invited = $this->groupRequestRepository->findInvitedForUserAndGroup($user, $group);
        if (null !== $invited) {
            return ['state' => 'invited', 'request' => $invited, 'refused_count' => $refusedCount];
        }

        if ($refusedCount >= GroupRequestRepository::MAX_REQUESTS_AFTER_REFUSAL) {
            return ['state' => 'blocked', 'request' => null, 'refused_count' => $refusedCount];
        }

        return ['state' => 'available', 'request' => null, 'refused_count' => $refusedCount];
    }

    public function createJoinRequest(User $user, Group $group): GroupRequest
    {
        if ($this->groupAccess->isMember($user, $group)) {
            throw new \DomainException('Tu es déjà membre de ce groupe.');
        }

        if ($this->groupAccess->isBannedInGroup($user, $group)) {
            throw new \DomainException('Tu es banni de ce groupe.');
        }

        if (!$this->groupRequestRepository->canUserRequestAgain($user, $group)) {
            throw new \DomainException('Tu as atteint la limite de demandes pour ce groupe.');
        }

        if (null !== $this->groupRequestRepository->findInvitedForUserAndGroup($user, $group)) {
            throw new \DomainException('Tu as déjà une invitation en attente pour ce groupe.');
        }

        $request = (new GroupRequest())
            ->setUser($user)
            ->setRelatedGroup($group)
            ->setStatus(GroupRequestStatus::Pending);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function inviteUser(User $inviter, Group $group, User $target): GroupRequest
    {
        if (!$this->groupAccess->isStaff($inviter, $group)) {
            throw new \DomainException('Seul le chef ou un modérateur peut inviter.');
        }

        if ($this->groupAccess->isMember($target, $group)) {
            throw new \DomainException('Cet utilisateur est déjà membre du groupe.');
        }

        if ($this->groupAccess->isBannedInGroup($target, $group)) {
            throw new \DomainException('Cet utilisateur est banni de ce groupe.');
        }

        if (null !== $this->groupRequestRepository->findPendingForUserAndGroup($target, $group)) {
            throw new \DomainException('Une demande d\'adhésion est déjà en attente pour cet utilisateur.');
        }

        if (null !== $this->groupRequestRepository->findInvitedForUserAndGroup($target, $group)) {
            throw new \DomainException('Cet utilisateur a déjà une invitation en attente.');
        }

        $request = (new GroupRequest())
            ->setUser($target)
            ->setRelatedGroup($group)
            ->setStatus(GroupRequestStatus::Invited);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function acceptJoinRequest(User $staff, GroupRequest $request): void
    {
        $this->assertStaffCanHandle($staff, $request);
        $this->assertRequestStillPending($request);

        $this->addMemberFromRequest($request, GroupRequestStatus::Accepted);
    }

    public function refuseJoinRequest(User $staff, GroupRequest $request): void
    {
        $this->assertStaffCanHandle($staff, $request);
        $this->assertRequestStillPending($request);

        $request->setStatus(GroupRequestStatus::Refused);
        $request->markAsRead();
        $this->entityManager->flush();
    }

    public function acceptInvitation(User $user, GroupRequest $request): void
    {
        $this->assertInvitationForUser($user, $request);
        $this->addMemberFromRequest($request, GroupRequestStatus::Accepted);
    }

    public function refuseInvitation(User $user, GroupRequest $request): void
    {
        $this->assertInvitationForUser($user, $request);

        $request->setStatus(GroupRequestStatus::Refused);
        $request->markAsRead();
        $this->entityManager->flush();
    }

    public function markPendingRequestsAsRead(Group $group): void
    {
        $this->groupRequestRepository->markPendingAsReadForGroups([$group->getId()]);
    }

    public function markHubNotificationsAsRead(User $user): void
    {
        $this->groupRequestRepository->markInvitationsAsReadForUser($user);
        $staffGroupIds = $this->groupMemberRepository->findStaffGroupIdsForUser($user);
        $this->groupRequestRepository->markPendingAsReadForGroups($staffGroupIds);
    }

    /**
     * @return list<GroupRequest>
     */
    public function findReceivedInvitations(User $user): array
    {
        return $this->groupRequestRepository->findInvitedForUser($user);
    }

    /**
     * @return list<GroupRequest>
     */
    public function findStaffJoinRequests(User $user): array
    {
        $staffGroupIds = $this->groupMemberRepository->findStaffGroupIdsForUser($user);

        return $this->groupRequestRepository->findPendingForGroups($staffGroupIds);
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, array{pending_or_invited: bool, refused_count: int}>
     */
    public function buildInviteStatusMap(Group $group, array $userIds): array
    {
        return $this->groupRequestRepository->buildInviteStatusMap($group, $userIds);
    }

    private function addMemberFromRequest(GroupRequest $request, GroupRequestStatus $finalStatus): void
    {
        $this->entityManager->refresh($request);

        if (!$request->isPending() && GroupRequestStatus::Invited !== $request->getStatus()) {
            throw new \DomainException('Cette demande n\'est plus en attente.');
        }

        $user = $request->getUser();
        $group = $request->getRelatedGroup();

        if ($this->groupAccess->isMember($user, $group)) {
            $request->setStatus($finalStatus);
            $request->markAsRead();
            $this->entityManager->flush();

            return;
        }

        $membership = (new GroupMember())
            ->setUser($user)
            ->setRole(GroupMemberRole::Member);
        $group->addGroupMember($membership);

        $request->setStatus($finalStatus);
        $request->markAsRead();

        $this->entityManager->persist($membership);
        $this->entityManager->flush();
    }

    private function assertRequestStillPending(GroupRequest $request): void
    {
        $this->entityManager->refresh($request);

        if (!$request->isPending()) {
            throw new \DomainException('Cette demande a déjà été traitée par un autre modérateur.');
        }
    }

    private function assertStaffCanHandle(User $staff, GroupRequest $request): void
    {
        if (!$this->groupAccess->isStaff($staff, $request->getRelatedGroup())) {
            throw new \DomainException('Accès refusé.');
        }
    }

    private function assertInvitationForUser(User $user, GroupRequest $request): void
    {
        if ($request->getUser()->getId() !== $user->getId()) {
            throw new \DomainException('Cette invitation ne t\'est pas destinée.');
        }

        $this->entityManager->refresh($request);

        if (GroupRequestStatus::Invited !== $request->getStatus()) {
            throw new \DomainException('Cette invitation n\'est plus valide.');
        }
    }
}
