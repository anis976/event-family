<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Controller\Admin\Crud\GroupCrudController;
use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\GroupMemberModerationService;
use App\Service\GroupOwnerTransferService;
use App\Service\GroupRequestService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('%ef.admin.path%/groupes', name: 'app_admin_groups')]
#[IsGranted(User::ROLE_MODERATOR)]
final class GroupModeratorAdminController extends AbstractAppController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly UserRepository $userRepository,
        private readonly GroupMemberModerationService $memberModerationService,
        private readonly GroupRequestService $groupRequestService,
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/{groupId}/membres/roles/reconcilier', name: '_reconcile_roles', requirements: ['groupId' => '\d+'], methods: ['POST'])]
    public function reconcileRoles(int $groupId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);

        if (!$this->isCsrfTokenValid('admin-reconcile-roles'.$groupId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId);
        }

        if ($group->isStaffCircle()) {
            $this->addErrorFlash($this->trans('admin.crud.group.error_staff_circle_members'));

            return $this->redirectToGroupEdit($groupId);
        }

        if ($this->groupOwnerTransfer->reconcileOwnerRoles($group)) {
            $this->addSuccessFlash($this->trans('admin.crud.group.roles_reconciled'));
        } else {
            $this->addSuccessFlash($this->trans('admin.crud.group.roles_already_ok'));
        }

        return $this->redirectToGroupEdit($groupId);
    }

    #[Route('/{groupId}/membres/{memberId}/moderateur', name: '_set_moderator', requirements: ['groupId' => '\d+', 'memberId' => '\d+'], methods: ['POST'])]
    public function setModerator(int $groupId, int $memberId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);
        $member = $this->requireMember($memberId, $group);

        if (!$this->isCsrfTokenValid('admin-set-moderator'.$memberId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId);
        }

        try {
            $this->memberModerationService->assignModeratorFromAdmin($group, $member->getUser());
            $this->addSuccessFlash($this->trans('admin.crud.group.moderator_assigned', [
                '%name%' => $member->getUser()->getAdminLabel(),
            ]));
        } catch (\DomainException $exception) {
            $this->addErrorFlash($this->trans($exception->getMessage()));
        }

        return $this->redirectToGroupEdit($groupId);
    }

    #[Route('/{groupId}/membres/{memberId}/moderateur/retirer', name: '_clear_moderator', requirements: ['groupId' => '\d+', 'memberId' => '\d+'], methods: ['POST'])]
    public function clearModerator(int $groupId, int $memberId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);
        $member = $this->requireMember($memberId, $group);

        if (!$this->isCsrfTokenValid('admin-clear-moderator'.$memberId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId);
        }

        try {
            $this->memberModerationService->clearModeratorFromAdmin($group, $member);
            $this->addSuccessFlash($this->trans('admin.crud.group.moderator_removed', [
                '%name%' => $member->getUser()->getAdminLabel(),
            ]));
        } catch (\DomainException $exception) {
            $this->addErrorFlash($this->trans($exception->getMessage()));
        }

        return $this->redirectToGroupEdit($groupId);
    }

    #[Route('/{groupId}/membres/{memberId}/retirer', name: '_remove_member', requirements: ['groupId' => '\d+', 'memberId' => '\d+'], methods: ['POST'])]
    public function removeMember(int $groupId, int $memberId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);
        $member = $this->requireMember($memberId, $group);

        if (!$this->isCsrfTokenValid('admin-remove-member'.$memberId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId);
        }

        try {
            $this->memberModerationService->removeMemberFromAdmin($group, $member);
            $this->addSuccessFlash($this->trans('admin.crud.group.member_removed', [
                '%name%' => $member->getUser()->getAdminLabel(),
            ]));
        } catch (\DomainException $exception) {
            $this->addErrorFlash($this->trans($exception->getMessage()));
        }

        return $this->redirectToGroupEdit($groupId);
    }

    #[Route('/{groupId}/membres/inviter/{userId}', name: '_invite_member', requirements: ['groupId' => '\d+', 'userId' => '\d+'], methods: ['POST'])]
    public function inviteMember(int $groupId, int $userId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);

        if (!$this->isCsrfTokenValid('admin-invite-member'.$userId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
        }

        $target = $this->userRepository->findActiveById($userId);
        if (null === $target) {
            $this->addErrorFlash($this->trans('admin.crud.group.error_member_not_found'));

            return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
        }

        try {
            $this->groupRequestService->inviteUserFromAdmin($group, $target);
            $this->addSuccessFlash($this->trans('admin.crud.group.member_invited', [
                '%name%' => $target->getAdminLabel(),
            ]));
        } catch (\DomainException $exception) {
            $this->addErrorFlash($this->trans($exception->getMessage()));
        }

        return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
    }

    #[Route('/{groupId}/membres/ajouter/{userId}', name: '_add_member', requirements: ['groupId' => '\d+', 'userId' => '\d+'], methods: ['POST'])]
    public function addMember(int $groupId, int $userId, Request $request): Response
    {
        $group = $this->requireGroup($groupId);

        if (!$this->isCsrfTokenValid('admin-add-member'.$userId, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
        }

        $target = $this->userRepository->findActiveById($userId);
        if (null === $target) {
            $this->addErrorFlash($this->trans('admin.crud.group.error_member_not_found'));

            return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
        }

        try {
            $this->groupRequestService->addMemberFromAdmin($group, $target);
            $this->addSuccessFlash($this->trans('admin.crud.group.member_added', [
                '%name%' => $target->getAdminLabel(),
            ]));
        } catch (\DomainException $exception) {
            $this->addErrorFlash($this->trans($exception->getMessage()));
        }

        return $this->redirectToGroupEdit($groupId, $request->request->get('member_q'));
    }

    private function requireGroup(int $groupId): \App\Entity\Group
    {
        $group = $this->groupRepository->find($groupId);
        if (null === $group) {
            throw $this->createNotFoundException();
        }

        return $group;
    }

    private function requireMember(int $memberId, \App\Entity\Group $group): \App\Entity\GroupMember
    {
        $member = $this->groupMemberRepository->findOneWithGroupAndUser($memberId);
        if (null === $member || $member->getGroup()->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }

        return $member;
    }

    private function redirectToGroupEdit(int $groupId, mixed $memberSearchQuery = null): Response
    {
        $urlGenerator = $this->adminUrlGenerator
            ->setController(GroupCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($groupId);

        $searchQuery = \is_string($memberSearchQuery) ? trim($memberSearchQuery) : '';
        if ('' !== $searchQuery) {
            $urlGenerator->set('member_q', $searchQuery);
        }

        return $this->redirect($urlGenerator->generateUrl());
    }
}
