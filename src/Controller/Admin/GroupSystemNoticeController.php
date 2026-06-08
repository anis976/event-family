<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Entity\User;
use App\Form\GroupSystemNoticeFormType;
use App\Repository\GroupRepository;
use App\Service\GroupSystemNoticeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('%ef.admin.path%/groupes', name: 'app_admin_groups')]
#[IsGranted(User::ROLE_ADMIN)]
final class GroupSystemNoticeController extends AbstractAppController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupSystemNoticeService $systemNoticeService,
    ) {
    }

    #[Route('/{id}/message-system', name: '_system_notice_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $group = $this->requireGroup($id);
        $admin = $this->requireUser();

        $form = $this->createForm(GroupSystemNoticeFormType::class, [
            'content' => $this->systemNoticeService->getContent($group),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->systemNoticeService->updateNotice(
                $group,
                $admin,
                (string) $form->get('content')->getData(),
            );
            $this->addSuccessFlash('flash.group.system_notice_updated');

            return $this->redirectToRoute('app_messages_group', ['groupId' => $group->getId()]);
        }

        return $this->render('admin/group_system_notice/edit.html.twig', [
            'group' => $group,
            'form' => $form,
            'is_customized' => $this->systemNoticeService->isCustomized($group),
        ]);
    }

    #[Route('/{id}/message-system/reinitialiser', name: '_system_notice_reset', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reset(int $id, Request $request): Response
    {
        $group = $this->requireGroup($id);
        $admin = $this->requireUser();

        if (!$this->isCsrfTokenValid('reset-system-notice'.$group->getId(), (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_messages_group', ['groupId' => $group->getId()]);
        }

        $this->systemNoticeService->resetToDefault($group, $admin);
        $this->addSuccessFlash('flash.group.system_notice_reset');

        return $this->redirectToRoute('app_messages_group', ['groupId' => $group->getId()]);
    }

    private function requireGroup(int $id): \App\Entity\Group
    {
        $group = $this->groupRepository->find($id);
        if (null === $group) {
            throw $this->createNotFoundException();
        }

        return $group;
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException($this->trans('admin.access.session_changed'));
        }

        return $user;
    }
}
