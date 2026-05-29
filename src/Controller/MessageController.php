<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Form\GroupMessageFormType;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\MessageService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages', name: 'app_messages')]
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractAppController
{
    public function __construct(
        private readonly MessageService $messageService,
        private readonly MessageRepository $messageRepository,
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function hub(): Response
    {
        $user = $this->requireUser();

        return $this->render('messages/index.html.twig', [
            'notifications' => $this->messageService->getUnreadCounts($user),
        ]);
    }

    #[Route('/prives', name: '_private', methods: ['GET'])]
    public function privateMessages(): Response
    {
        $user = $this->requireUser();
        $threads = $this->messageRepository->findPrivateRootThreadsForUser($user);

        return $this->render('messages/private.html.twig', [
            'private_threads' => $threads,
            'unread_ids' => array_flip($this->messageService->collectUnreadIds($user, $threads)),
        ]);
    }

    #[Route('/groupe/{groupId?}', name: '_group', requirements: ['groupId' => '\d+'], methods: ['GET', 'POST'])]
    public function groupMessages(Request $request, ?int $groupId = null): Response
    {
        $user = $this->requireUser();
        $userGroups = $this->groupMemberRepository->findGroupsForUser($user);
        $userHasGroup = [] !== $userGroups;

        $currentGroup = null;
        if ($userHasGroup) {
            if (null !== $groupId) {
                foreach ($userGroups as $group) {
                    if ($group->getId() === $groupId) {
                        $currentGroup = $group;
                        break;
                    }
                }
            }
            $currentGroup ??= $userGroups[0];
        }

        $form = $this->createForm(GroupMessageFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== $currentGroup) {
            try {
                $this->messageService->sendGroupMessage(
                    $user,
                    $currentGroup,
                    (string) $form->get('content')->getData(),
                );
                $this->addSuccessFlash('Message publié.');
            } catch (\DomainException $e) {
                $this->addErrorFlash($e->getMessage());
            }

            return $this->redirectToRoute('app_messages_group', ['groupId' => $currentGroup->getId()]);
        }

        $groupThreads = null !== $currentGroup
            ? $this->messageRepository->findGroupRootThreads($currentGroup)
            : [];

        return $this->render('messages/group.html.twig', [
            'user_groups' => $userGroups,
            'user_has_group' => $userHasGroup,
            'current_group' => $currentGroup,
            'group_threads' => $groupThreads,
            'unread_ids' => array_flip($this->messageService->collectUnreadIds($user, $groupThreads)),
            'form' => $form,
        ]);
    }

    #[Route('/direct', name: '_send_direct', methods: ['POST'])]
    public function sendDirect(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('send_direct_message', (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Réessaie.');

            return $this->redirectToRoute('app_home');
        }

        $sender = $this->requireUser();
        $recipientId = (int) $request->request->get('recipient_id', 0);
        $content = trim((string) $request->request->get('content', ''));

        $recipient = $this->userRepository->findActiveById($recipientId);
        if (null === $recipient) {
            $this->addErrorFlash('Destinataire introuvable.');

            return $this->redirectAfterDirectMessage($request, $recipientId);
        }

        if ('' === $content) {
            $this->addErrorFlash('Le message ne peut pas être vide.');

            return $this->redirectAfterDirectMessage($request, $recipientId);
        }

        try {
            $this->messageService->sendPrivateMessage($sender, $recipient, $content);
            $this->addSuccessFlash('Message envoyé.');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterDirectMessage($request, $recipientId);
    }

    #[Route('/reponse/{id}', name: '_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reply(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $message = $this->messageRepository->findOneWithRelations($id);
        if (null === $message) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('reply'.$id, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Réessaie.');

            return $this->redirectAfterMessageAction($request, $message);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ('' === $content) {
            $this->addErrorFlash('Le message ne peut pas être vide.');

            return $this->redirectAfterMessageAction($request, $message);
        }

        try {
            $this->messageService->reply($user, $message, $content);
            $this->addSuccessFlash('Réponse envoyée.');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectAfterMessageAction($request, $message);
    }

    #[Route('/supprimer/{id}', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $message = $this->messageRepository->findOneWithRelations($id);
        if (null === $message) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Réessaie.');

            return $this->redirectAfterMessageAction($request, $message);
        }

        try {
            $this->messageService->deleteMessage($user, $message);
            $this->addSuccessFlash('Message supprimé.');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        if ($message->isGroupMessage()) {
            $group = $message->getRelatedGroup();

            return $this->redirectToRoute('app_messages_group', ['groupId' => $group?->getId()]);
        }

        return $this->redirectToRoute('app_messages_private');
    }

    #[Route('/lire/{id}', name: '_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        $message = $this->messageRepository->find($id);
        if (null === $message) {
            return $this->json(['status' => 'error'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->messageService->markAsRead($message, $user);
        } catch (\DomainException) {
            return $this->json(['status' => 'error'], Response::HTTP_FORBIDDEN);
        }

        return $this->json(['status' => 'success']);
    }

    private function redirectAfterDirectMessage(Request $request, int $recipientId): Response
    {
        $redirectRoute = (string) $request->request->get('redirect_route', '');

        if ('app_groups_show' === $redirectRoute) {
            $redirectId = (int) $request->request->get('redirect_id', 0);
            if ($redirectId > 0) {
                return $this->redirectToRoute('app_groups_show', ['id' => $redirectId]);
            }
        }

        if ('app_messages_private' === $redirectRoute) {
            return $this->redirectToRoute('app_messages_private');
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $recipientId]);
    }

    private function redirectAfterMessageAction(Request $request, \App\Entity\Message $message): Response
    {
        if ($message->isGroupMessage()) {
            return $this->redirectToRoute('app_messages_group', [
                'groupId' => $message->getRelatedGroup()?->getId(),
            ]);
        }

        return $this->redirectToRoute('app_messages_private');
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
