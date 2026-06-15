<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Controller\Admin\Crud\GroupCrudController;
use App\Entity\User;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Service\GroupMemberModerationService;
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
        private readonly GroupMemberModerationService $memberModerationService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
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

    private function redirectToGroupEdit(int $groupId): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(GroupCrudController::class)
                ->setAction(Action::EDIT)
                ->setEntityId($groupId)
                ->generateUrl(),
        );
    }
}
