<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Form\GroupMessageFormType;
use App\Form\StaffAnnouncementFormType;
use App\Form\StaffPrivateMessageFormType;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\GroupAccessService;
use App\Service\GroupSystemNoticeService;
use App\Service\MessageService;
use App\Service\SiteStaffService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        private readonly GroupSystemNoticeService $systemNoticeService,
        private readonly GroupAccessService $groupAccess,
        private readonly SiteStaffService $siteStaff,
        #[Autowire('%ef.messages.group_threads_initial%')]
        private readonly int $groupThreadsInitial,
        #[Autowire('%ef.messages.group_threads_load_more%')]
        private readonly int $groupThreadsLoadMore,
        #[Autowire('%ef.messages.group_threads_max_visible%')]
        private readonly int $groupThreadsMaxVisible,
        #[Autowire('%ef.messages.private_threads_initial%')]
        private readonly int $privateThreadsInitial,
        #[Autowire('%ef.messages.private_threads_load_more%')]
        private readonly int $privateThreadsLoadMore,
        #[Autowire('%ef.messages.private_threads_max_visible%')]
        private readonly int $privateThreadsMaxVisible,
        #[Autowire('%ef.messages.private_replies_initial%')]
        private readonly int $privateRepliesInitial,
        #[Autowire('%ef.messages.private_replies_load_more%')]
        private readonly int $privateRepliesLoadMore,
        #[Autowire('%ef.messages.private_replies_max_visible%')]
        private readonly int $privateRepliesMaxVisible,
        #[Autowire('%ef.message_photos.max_bytes%')]
        private readonly int $messagePhotoMaxBytes,
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

    #[Route('/prives', name: '_private', methods: ['GET', 'POST'])]
    public function privateMessages(Request $request): Response
    {
        $user = $this->requireUser();
        $isSiteStaff = $this->siteStaff->isSiteStaff($user);
        $staffPrivateForm = null;

        if ($isSiteStaff) {
            $staffPrivateForm = $this->createForm(StaffPrivateMessageFormType::class, null, [
                'notice_variant_choices' => $this->buildStaffNoticeVariantChoices($user),
            ]);
            $staffPrivateForm->handleRequest($request);

            if ($staffPrivateForm->isSubmitted() && $staffPrivateForm->isValid()) {
                try {
                    $recipient = $this->userRepository->resolveActiveRecipientFromQuery(
                        (string) $staffPrivateForm->get('recipientQuery')->getData(),
                    );
                    $sendMode = (string) $staffPrivateForm->get('noticeVariant')->getData();
                    $variant = $this->siteStaff->resolvePrivateNoticeVariant($user, $sendMode);

                    $this->messageService->sendPlatformPrivateNotice(
                        $recipient,
                        (string) $staffPrivateForm->get('content')->getData(),
                        $variant,
                        $user,
                    );
                    $this->addSuccessFlash('flash.message.staff_sent_private');
                } catch (\DomainException $e) {
                    $this->addErrorFlash($e->getMessage());
                }

                return $this->redirectToRoute('app_messages_private');
            }
        }

        $threadsVisible = $this->resolveThreadsVisibleCount(
            $request,
            $this->privateThreadsInitial,
            $this->privateThreadsMaxVisible,
        );
        $repliesVisibleByRoot = $this->resolveRepliesVisibleByRoot(
            $request,
            $this->privateRepliesInitial,
            $this->privateRepliesMaxVisible,
        );
        $threads = $this->messageRepository->findPrivateRootThreadsForUser(
            $user,
            $threadsVisible,
            $repliesVisibleByRoot,
            $this->privateRepliesInitial,
        );
        $threadsTotal = \count($threads) < $threadsVisible
            ? \count($threads)
            : $this->messageRepository->countPrivateRootThreadsForUser($user);
        $canLoadOlder = $threadsTotal > $threadsVisible && $threadsVisible < $this->privateThreadsMaxVisible;
        $nextVisible = min(
            $threadsVisible + max(1, $this->privateThreadsLoadMore),
            $this->privateThreadsMaxVisible,
        );
        $maxVisibleReached = $threadsTotal > $this->privateThreadsMaxVisible
            && $threadsVisible >= $this->privateThreadsMaxVisible;

        $rootIds = array_values(array_filter(array_map(
            static fn (\App\Entity\Message $message): ?int => $message->getId(),
            $threads,
        )));
        $threadReplyTotals = $this->messageRepository->countRepliesByRootIds($rootIds);
        $threadRepliesVisible = [];
        foreach ($rootIds as $rootId) {
            $threadRepliesVisible[$rootId] = $repliesVisibleByRoot[$rootId] ?? $this->privateRepliesInitial;
        }

        return $this->render('messages/private.html.twig', [
            'private_threads' => $threads,
            'private_threads_total' => $threadsTotal,
            'can_load_older_threads' => $canLoadOlder,
            'next_threads_visible' => $nextVisible,
            'threads_max_visible_reached' => $maxVisibleReached,
            'threads_max_visible' => $this->privateThreadsMaxVisible,
            'unread_ids' => array_flip($this->messageService->collectUnreadIds($user, $threads)),
            'read_receipts' => $this->messageService->collectReadReceiptsForViewer($user, $threads),
            'thread_reply_totals' => $threadReplyTotals,
            'thread_replies_visible' => $threadRepliesVisible,
            'private_replies_load_more' => $this->privateRepliesLoadMore,
            'private_replies_max_visible' => $this->privateRepliesMaxVisible,
            'staff_private_form' => $staffPrivateForm?->createView(),
        ]);
    }

    #[Route('/groupe/{groupId?}', name: '_group', requirements: ['groupId' => '\d+'], methods: ['GET', 'POST'], defaults: ['groupId' => null])]
    public function groupMessages(Request $request, ?int $groupId = null): Response
    {
        $user = $this->requireUser();
        $userGroups = $this->groupMemberRepository->findGroupsForMessaging($user);
        $userHasGroup = [] !== $userGroups;
        $showGroupUnreadDots = \count($userGroups) > 1;
        $isSiteStaff = $this->siteStaff->isSiteStaff($user);

        $currentGroup = null;
        $isMemberOfCurrent = false;

        if (null !== $groupId) {
            foreach ($userGroups as $group) {
                if ($group->getId() === $groupId) {
                    $currentGroup = $group;
                    $isMemberOfCurrent = true;
                    break;
                }
            }

            if (null === $currentGroup && $isSiteStaff) {
                $currentGroup = $this->groupRepository->find($groupId);
                if (null === $currentGroup) {
                    $this->addErrorFlash('flash.message.group_not_found');

                    return $this->redirectToRoute('app_messages_group');
                }

                $isMemberOfCurrent = $this->groupAccess->isMember($user, $currentGroup);
            } elseif (null === $currentGroup) {
                $this->addErrorFlash('flash.message.group_inaccessible');

                return $this->redirectToRoute('app_messages_group');
            }
        } elseif ($userHasGroup) {
            $currentGroup = $userGroups[0];
            $isMemberOfCurrent = true;
        }

        $form = $this->createForm(GroupMessageFormType::class);
        $staffForm = $this->createForm(StaffAnnouncementFormType::class);

        $form->handleRequest($request);
        $staffForm->handleRequest($request);

        if ($staffForm->isSubmitted() && $staffForm->isValid() && null !== $currentGroup) {
            if (!$isSiteStaff) {
                throw $this->createAccessDeniedException();
            }

            try {
                $this->messageService->sendStaffAnnouncement(
                    $user,
                    $currentGroup,
                    (string) $staffForm->get('content')->getData(),
                );
                $this->addSuccessFlash('flash.message.staff_published');
            } catch (\DomainException $e) {
                $this->addErrorFlash($e->getMessage());
            }

            return $this->redirectToRoute('app_messages_group', ['groupId' => $currentGroup->getId()]);
        }

        if ($form->isSubmitted() && $form->isValid() && null !== $currentGroup) {
            if (!$isMemberOfCurrent) {
                throw $this->createAccessDeniedException();
            }

            try {
                $uploadedPhotos = $this->extractGroupMessagePhotos($request);
                $photoCrops = $this->extractGroupMessagePhotoCrops($request, \count($uploadedPhotos));

                $this->messageService->sendGroupMessage(
                    $user,
                    $currentGroup,
                    (string) $form->get('content')->getData(),
                    $uploadedPhotos,
                    $photoCrops,
                );
                $this->addSuccessFlash('flash.message.published');
            } catch (\DomainException $e) {
                $this->addErrorFlash($e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $this->addErrorFlash($e->getMessage());
            }

            return $this->redirectToRoute('app_messages_group', ['groupId' => $currentGroup->getId()]);
        }

        $groupThreadsTotal = 0;
        $groupThreadsVisible = 0;
        $groupThreads = [];
        $canLoadOlderThreads = false;
        $nextThreadsVisible = 0;
        $threadsMaxVisibleReached = false;

        if ($request->isMethod('GET') && null !== $currentGroup && $isMemberOfCurrent) {
            $this->messageService->markGroupMessagesAsViewed($user, $currentGroup);
        }

        if (null !== $currentGroup) {
            $groupThreadsTotal = $this->messageRepository->countGroupRootThreads($currentGroup);
            $groupThreadsVisible = $this->resolveThreadsVisibleCount(
                $request,
                $this->groupThreadsInitial,
                $this->groupThreadsMaxVisible,
            );
            $groupThreads = $this->messageRepository->findGroupRootThreads($currentGroup, $groupThreadsVisible);
            $canLoadOlderThreads = $groupThreadsTotal > $groupThreadsVisible
                && $groupThreadsVisible < $this->groupThreadsMaxVisible;
            $nextThreadsVisible = min(
                $groupThreadsVisible + max(1, $this->groupThreadsLoadMore),
                $this->groupThreadsMaxVisible,
            );
            $threadsMaxVisibleReached = $groupThreadsTotal > $this->groupThreadsMaxVisible
                && $groupThreadsVisible >= $this->groupThreadsMaxVisible;
        }

        $groupIds = array_map(static fn (Group $g): int => (int) $g->getId(), $userGroups);
        $unreadByGroupId = $showGroupUnreadDots
            ? $this->messageService->getUnreadCountByGroupIds($user, $groupIds)
            : [];

        return $this->render('messages/group.html.twig', [
            'user_groups' => $userGroups,
            'user_has_group' => $userHasGroup,
            'show_group_unread_dots' => $showGroupUnreadDots,
            'unread_by_group_id' => $unreadByGroupId,
            'current_group' => $currentGroup,
            'is_member_of_current' => $isMemberOfCurrent,
            'is_site_staff' => $isSiteStaff,
            'group_threads' => $groupThreads,
            'group_threads_total' => $groupThreadsTotal,
            'group_threads_visible' => $groupThreadsVisible,
            'can_load_older_threads' => $canLoadOlderThreads,
            'next_threads_visible' => $nextThreadsVisible,
            'threads_max_visible_reached' => $threadsMaxVisibleReached,
            'threads_max_visible' => $this->groupThreadsMaxVisible,
            'unread_ids' => array_flip($this->messageService->collectUnreadIds($user, $groupThreads)),
            'form' => $form,
            'staff_form' => $staffForm,
            'system_notice_content' => null !== $currentGroup
                ? $this->systemNoticeService->getContent($currentGroup)
                : '',
            'system_notice_is_custom' => null !== $currentGroup
                && $this->systemNoticeService->isCustomized($currentGroup),
            'message_photo_max_bytes' => $this->messagePhotoMaxBytes,
        ]);
    }

    #[Route('/direct', name: '_send_direct', methods: ['POST'])]
    public function sendDirect(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('send_direct_message', (string) $request->request->get('_token'))) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_home');
        }

        $sender = $this->requireUser();
        $recipientId = (int) $request->request->get('recipient_id', 0);
        $content = trim((string) $request->request->get('content', ''));

        $recipient = $this->userRepository->findActiveById($recipientId);
        if (null === $recipient) {
            $this->addErrorFlash('flash.message.recipient_not_found');

            return $this->redirectAfterDirectMessage($request, $recipientId);
        }

        if ('' === $content) {
            $this->addErrorFlash('flash.message.empty');

            return $this->redirectAfterDirectMessage($request, $recipientId);
        }

        $sendMode = (string) $request->request->get('send_mode', 'member');
        $sendAsStaff = \in_array($sendMode, ['staff', 'staff_moderator', 'staff_admin'], true);

        try {
            if ($sendAsStaff) {
                $variant = $this->siteStaff->resolvePrivateNoticeVariant($sender, $sendMode);

                $this->messageService->sendPlatformPrivateNotice(
                    $recipient,
                    $content,
                    $variant,
                    $sender,
                );
                $this->addSuccessFlash('flash.message.staff_sent_private');
            } else {
                $this->messageService->sendPrivateMessage($sender, $recipient, $content);
                $this->addSuccessFlash('flash.message.sent');
            }
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
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectAfterMessageAction($request, $message);
        }

        $content = trim((string) $request->request->get('content', ''));
        if ('' === $content) {
            $this->addErrorFlash('flash.message.empty');

            return $this->redirectAfterMessageAction($request, $message);
        }

        try {
            $this->messageService->reply($user, $message, $content);
            $this->addSuccessFlash('flash.message.reply_sent');
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
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectAfterMessageAction($request, $message);
        }

        try {
            $isGroup = $message->isGroupMessage();
            $this->messageService->deleteMessage($user, $message);
            $this->addSuccessFlash($isGroup ? 'flash.message.deleted_group' : 'flash.message.hidden_private');
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

    private function resolveThreadsVisibleCount(Request $request, int $initial, int $maxVisible): int
    {
        $initial = max(1, $initial);
        $max = max($initial, $maxVisible);
        $requested = $request->query->getInt('show', $initial);

        return max($initial, min($requested, $max));
    }

    /**
     * @return array<int, int> rootId => nombre de réponses visibles
     */
    private function resolveRepliesVisibleByRoot(Request $request, int $initial, int $maxVisible): array
    {
        $initial = max(1, $initial);
        $max = max($initial, $maxVisible);
        $requested = $request->query->all('replies');
        if (!\is_array($requested)) {
            return [];
        }

        $map = [];
        foreach ($requested as $rootId => $count) {
            $id = (int) $rootId;
            if ($id <= 0) {
                continue;
            }

            $map[$id] = max($initial, min((int) $count, $max));
        }

        return $map;
    }

    /**
     * @return list<\Symfony\Component\HttpFoundation\File\UploadedFile>
     */
    private function extractGroupMessagePhotos(Request $request): array
    {
        $files = [];
        $bag = $request->files;

        for ($index = 0; $index < 2; ++$index) {
            $file = $bag->get('photo_'.$index);
            if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                $files[] = $file;
            }
        }

        $legacy = $bag->all('photos');
        if (\is_array($legacy)) {
            foreach ($legacy as $file) {
                if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile && $file->isValid()) {
                    $files[] = $file;
                }
            }
        }

        return \array_slice($files, 0, 2);
    }

    /**
     * @return list<array{x: int, y: int, width: int, height: int}|null>
     */
    private function extractGroupMessagePhotoCrops(Request $request, int $photoCount): array
    {
        $crops = [];
        for ($index = 0; $index < $photoCount; ++$index) {
            $crops[] = $this->parsePhotoCrop($request, 'photo_'.$index);
        }

        return $crops;
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function parsePhotoCrop(Request $request, string $prefix): ?array
    {
        $width = (int) $request->request->get($prefix.'_cropWidth');
        $height = (int) $request->request->get($prefix.'_cropHeight');
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'x' => (int) $request->request->get($prefix.'_cropX'),
            'y' => (int) $request->request->get($prefix.'_cropY'),
            'width' => $width,
            'height' => $height,
        ];
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function buildStaffNoticeVariantChoices(User $user): array
    {
        $choices = [
            $this->trans('ui.messages.direct.mode_moderator') => 'staff_moderator',
        ];

        if ($this->siteStaff->isSiteAdmin($user)) {
            $choices[$this->trans('ui.messages.direct.mode_admin')] = 'staff_admin';
        }

        return $choices;
    }
}
