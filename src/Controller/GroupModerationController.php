<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\GroupRequestRepository;
use App\Repository\UserRepository;
use App\Service\GroupAccessService;
use App\Service\GroupMemberModerationService;
use App\Service\GroupOwnerTransferService;
use App\Service\GroupRequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/groupes', name: 'app_groups')]
#[IsGranted('ROLE_USER')]
final class GroupModerationController extends AbstractAppController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupRequestRepository $groupRequestRepository,
        private readonly UserRepository $userRepository,
        private readonly GroupAccessService $groupAccess,
        private readonly GroupRequestService $groupRequestService,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly GroupMemberModerationService $memberModerationService,
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
    ) {
    }

    #[Route('/{id}/demandes', name: '_manage_requests', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function manageRequests(int $id): Response
    {
        $user = $this->requireUser();
        $group = $this->requireModeratableGroup($id);

        if (!$this->groupAccess->isStaff($user, $group)) {
            throw $this->createAccessDeniedException();
        }

        $this->groupRequestService->markPendingRequestsAsRead($group);

        return $this->render('groups/manage_requests.html.twig', [
            'group' => $group,
            'requests' => $this->groupRequestRepository->findPendingByGroupWithUser($group),
        ]);
    }

    #[Route('/{id}/demandes/{requestId}/accepter', name: '_accept_request', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function acceptRequest(int $id, int $requestId, Request $request): Response
    {
        return $this->handleStaffRequestAction($id, $requestId, $request, 'accept-join', function (User $staff, $groupRequest): void {
            $this->groupRequestService->acceptJoinRequest($staff, $groupRequest);
        }, 'flash.group.request_accepted');
    }

    #[Route('/{id}/demandes/{requestId}/refuser', name: '_refuse_request', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function refuseRequest(int $id, int $requestId, Request $request): Response
    {
        return $this->handleStaffRequestAction($id, $requestId, $request, 'refuse-join', function (User $staff, $groupRequest): void {
            $this->groupRequestService->refuseJoinRequest($staff, $groupRequest);
        }, 'flash.group.request_refused');
    }

    #[Route('/{id}/inviter', name: '_invite_search', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function inviteSearch(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $group = $this->requireModeratableGroup($id);

        if (!$this->groupAccess->isStaff($user, $group)) {
            throw $this->createAccessDeniedException();
        }

        $searchQuery = trim((string) $request->query->get('q', ''));
        $memberIds = array_map(
            static fn ($member) => $member->getUser()->getId(),
            $group->getGroupMembers()->toArray(),
        );

        $browseUsers = $this->userRepository->findEligibleForGroupInvite($memberIds);
        $browseTotal = $this->userRepository->countEligibleForGroupInvite($memberIds);

        $searchUsers = '' !== $searchQuery && mb_strlen($searchQuery) >= 2
            ? $this->userRepository->searchForGroupInvite($memberIds, $searchQuery)
            : [];

        $allUserIds = array_unique(array_merge(
            array_map(static fn (User $u): int => $u->getId(), $browseUsers),
            array_map(static fn (User $u): int => $u->getId(), $searchUsers),
        ));
        $userStatusRules = $this->groupRequestService->buildInviteStatusMap($group, array_values($allUserIds));

        return $this->render('groups/invite.html.twig', [
            'group' => $group,
            'searchQuery' => $searchQuery,
            'searchUsers' => $searchUsers,
            'browseUsers' => $browseUsers,
            'browseTotal' => $browseTotal,
            'userStatusRules' => $userStatusRules,
        ]);
    }

    #[Route('/{id}/inviter/{userId}', name: '_invite_user', requirements: ['id' => '\d+', 'userId' => '\d+'], methods: ['POST'])]
    public function inviteUser(int $id, int $userId, Request $request): Response
    {
        $staff = $this->requireUser();
        $group = $this->requireModeratableGroup($id);

        if (!$this->groupAccess->isStaff($staff, $group)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('invite'.$userId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_groups_invite_search', ['id' => $group->getId()]);
        }

        $target = $this->userRepository->findActiveById($userId);
        if (null === $target) {
            $this->addErrorFlash('flash.group.user_not_found');

            return $this->redirectToRoute('app_groups_invite_search', ['id' => $group->getId()]);
        }

        try {
            $this->groupRequestService->inviteUser($staff, $group, $target);
            $this->addSuccessFlash('flash.group.invite_sent', ['%name%' => $target->getFirstName()]);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectToRoute('app_groups_invite_search', [
            'id' => $group->getId(),
            'q' => $request->request->get('q'),
        ]);
    }

    #[Route('/{id}/rejoindre', name: '_request_join', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function requestJoin(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $group = $this->requireGroup($id);

        if (!$this->isCsrfTokenValid('join-group'.$group->getId(), (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        try {
            $this->groupRequestService->createJoinRequest($user, $group);
            $this->addSuccessFlash('flash.group.join_request_sent');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
    }

    #[Route('/{id}/invitation/{requestId}/accepter', name: '_accept_invitation', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function acceptInvitation(int $id, int $requestId, Request $request): Response
    {
        return $this->handleUserInvitationAction($id, $requestId, $request, 'accept-invitation', function (User $user, $groupRequest): void {
            $this->groupRequestService->acceptInvitation($user, $groupRequest);
        }, 'flash.group.invitation_joined');
    }

    #[Route('/{id}/invitation/{requestId}/refuser', name: '_refuse_invitation', requirements: ['id' => '\d+', 'requestId' => '\d+'], methods: ['POST'])]
    public function refuseInvitation(int $id, int $requestId, Request $request): Response
    {
        return $this->handleUserInvitationAction($id, $requestId, $request, 'refuse-invitation', function (User $user, $groupRequest): void {
            $this->groupRequestService->refuseInvitation($user, $groupRequest);
        }, 'flash.group.invitation_declined');
    }

    #[Route('/membres/{memberId}/transferer-chef', name: '_transfer_ownership', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    public function transferOwnership(int $memberId, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $actor = $this->requireUser();
        $member = $this->groupMemberRepository->findOneWithGroupAndUser($memberId);
        if (null === $member) {
            throw $this->createNotFoundException();
        }

        $group = $member->getGroup();
        $successor = $member->getUser();

        if (!$this->groupAccess->isOwner($actor, $group)) {
            throw $this->createAccessDeniedException();
        }

        if ($successor->getId() === $actor->getId()) {
            $this->addErrorFlash('flash.group.transfer_self');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        if (!$this->isCsrfTokenValid('transfer-owner'.$memberId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        $payload = $request->request->all('transfer_ownership');
        if (!\is_array($payload)) {
            $this->addErrorFlash('flash.group.transfer_invalid');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        $password = trim((string) ($payload['currentPassword'] ?? ''));
        $becomeModerator = isset($payload['becomeModerator']);

        if ('' === $password) {
            $this->addErrorFlash('ui.profile.form.validation.current_password_required');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        if (!$passwordHasher->isPasswordValid($actor, $password)) {
            $this->addErrorFlash('ui.profile.form.validation.current_password_invalid');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        try {
            $this->groupOwnerTransfer->transferOwnershipByCurrentOwner(
                $actor,
                $group,
                $successor,
                $becomeModerator,
            );
            $this->addSuccessFlash('flash.group.ownership_transferred', [
                '%name%' => $successor->getFirstName(),
            ]);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
    }

    #[Route('/membres/{memberId}/modo', name: '_toggle_modo', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    public function toggleModerator(int $memberId, Request $request): Response
    {
        return $this->handleMemberAction($memberId, $request, 'toggle-modo', function (User $actor, GroupMember $member): void {
            $this->memberModerationService->toggleModerator($actor, $member);
        }, 'flash.group.moderator_updated');
    }

    #[Route('/membres/{memberId}/ban', name: '_ban_member', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    public function banMember(int $memberId, Request $request): Response
    {
        $reason = trim((string) $request->request->get('reason', ''));

        return $this->handleMemberAction($memberId, $request, 'ban', function (User $actor, GroupMember $member) use ($reason): void {
            $this->memberModerationService->banMember($actor, $member, $reason);
        }, 'flash.group.member_banned', requiresReason: true, reason: $reason);
    }

    #[Route('/membres/{memberId}/debannir', name: '_unban_member', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    public function unbanMember(int $memberId, Request $request): Response
    {
        return $this->handleMemberAction($memberId, $request, 'unban', function (User $actor, GroupMember $member): void {
            $this->memberModerationService->unbanMember($actor, $member);
        }, 'flash.group.member_unbanned');
    }

    #[Route('/membres/{memberId}/exclure', name: '_kick_member', requirements: ['memberId' => '\d+'], methods: ['POST'])]
    public function kickMember(int $memberId, Request $request): Response
    {
        return $this->handleMemberAction($memberId, $request, 'kick', function (User $actor, GroupMember $member): void {
            $this->memberModerationService->kickMember($actor, $member);
        }, 'flash.group.member_kicked');
    }

    #[Route('/{id}/quitter', name: '_leave', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function leaveGroup(int $id, Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->requireUser();
        $group = $this->requireModeratableGroup($id);

        if (!$this->groupAccess->isMember($user, $group)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('leave-group'.$group->getId(), (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        if ($this->groupAccess->isOwner($user, $group) && $this->groupOwnerTransfer->hasOtherMembers($group, $user)) {
            return $this->handleOwnerLeave($user, $group, $request, $passwordHasher);
        }

        try {
            $this->memberModerationService->leaveGroup($user, $group);
            $this->addSuccessFlash('flash.group.left');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterLeave($group);
    }

    private function handleOwnerLeave(
        User $owner,
        Group $group,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $successorId = (int) $request->request->get('successor_id', 0);
        if ($successorId <= 0) {
            $this->addErrorFlash('flash.group.leave_successor_required');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        $successor = $this->userRepository->findActiveById($successorId);
        if (null === $successor) {
            $this->addErrorFlash('flash.group.transfer_target_deleted');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        $password = trim((string) $request->request->get('current_password', ''));
        if ('' === $password) {
            $this->addErrorFlash('ui.profile.form.validation.current_password_required');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        if (!$passwordHasher->isPasswordValid($owner, $password)) {
            $this->addErrorFlash('ui.profile.form.validation.current_password_invalid');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        try {
            $this->memberModerationService->leaveGroupAsOwner($owner, $group, $successor);
            $this->addSuccessFlash('flash.group.left_as_owner', [
                '%name%' => $successor->getFirstName(),
            ]);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterLeave($group);
    }

    private function redirectAfterLeave(Group $group): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $this->groupAccess->isMember($user, $group)) {
            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        return $this->redirectToRoute('app_groups');
    }

    /**
     * @param callable(User, GroupMember): void $action
     */
    private function handleMemberAction(
        int $memberId,
        Request $request,
        string $csrfPrefix,
        callable $action,
        string $successMessage,
        bool $requiresReason = false,
        string $reason = '',
    ): Response {
        $actor = $this->requireUser();
        $member = $this->groupMemberRepository->findOneWithGroupAndUser($memberId);
        if (null === $member) {
            throw $this->createNotFoundException();
        }

        $group = $member->getGroup();

        if ($group->isStaffCircle()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->groupAccess->isMember($actor, $group)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid($csrfPrefix.$memberId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        if ($requiresReason && '' === $reason) {
            $this->addErrorFlash('flash.group.ban_reason_required');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        try {
            $action($actor, $member);
            $this->addSuccessFlash($successMessage);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
    }

    /**
     * @param callable(User, \App\Entity\GroupRequest): void $action
     */
    private function handleStaffRequestAction(
        int $groupId,
        int $requestId,
        Request $request,
        string $csrfPrefix,
        callable $action,
        string $successMessage,
    ): Response {
        $staff = $this->requireUser();
        $group = $this->requireModeratableGroup($groupId);

        if (!$this->groupAccess->isStaff($staff, $group)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid($csrfPrefix.$requestId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectAfterStaffRequest($request, $group);
        }

        $groupRequest = $this->groupRequestRepository->findOneForGroup($requestId, $group);
        if (null === $groupRequest) {
            throw $this->createNotFoundException();
        }

        try {
            $action($staff, $groupRequest);
            $this->addSuccessFlash($successMessage);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterStaffRequest($request, $group);
    }

    private function redirectAfterStaffRequest(Request $request, Group $group): Response
    {
        if ('app_invitations' === $request->request->get('redirect_route')) {
            return $this->redirectToRoute('app_invitations_index');
        }

        return $this->redirectToRoute('app_groups_manage_requests', ['id' => $group->getId()]);
    }

    private function redirectAfterUserInvitation(Request $request, Group $group): Response
    {
        if ('app_invitations' === $request->request->get('redirect_route')) {
            return $this->redirectToRoute('app_invitations_index');
        }

        return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
    }

    /**
     * @param callable(User, \App\Entity\GroupRequest): void $action
     */
    private function handleUserInvitationAction(
        int $groupId,
        int $requestId,
        Request $request,
        string $csrfPrefix,
        callable $action,
        string $successMessage,
    ): Response {
        $user = $this->requireUser();
        $group = $this->requireGroup($groupId);

        if (!$this->isCsrfTokenValid($csrfPrefix.$requestId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectAfterUserInvitation($request, $group);
        }

        $groupRequest = $this->groupRequestRepository->findOneForGroup($requestId, $group);
        if (null === $groupRequest) {
            throw $this->createNotFoundException();
        }

        try {
            $action($user, $groupRequest);
            $this->addSuccessFlash($successMessage);
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterUserInvitation($request, $group);
    }

    private function requireGroup(int $id): Group
    {
        $group = $this->groupRepository->findOneWithMembers($id);
        if (null === $group) {
            throw $this->createNotFoundException();
        }

        return $group;
    }

    private function requireModeratableGroup(int $id): Group
    {
        $group = $this->requireGroup($id);
        if ($group->isStaffCircle()) {
            throw $this->createAccessDeniedException();
        }

        return $group;
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
