<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Group;
use App\Entity\User;
use App\Enum\EventTimeFilter;
use App\Enum\EventPhotoSlot;
use App\Enum\GroupMemberRole;
use App\Form\EventFormType;
use App\Repository\EventRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserRepository;
use App\Service\EventAccessService;
use App\Service\EventImageService;
use App\Service\MessageService;
use App\Service\SiteStaffService;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenements', name: 'app_events')]
#[IsGranted('ROLE_USER')]
final class EventController extends AbstractAppController
{
    private const PER_PAGE = 9;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly UserRepository $userRepository,
        private readonly EventAccessService $eventAccess,
        private readonly EventImageService $eventImageService,
        private readonly MessageService $messageService,
        private readonly SiteStaffService $siteStaff,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $memberGroupIds = $this->groupMemberRepository->findGroupIdsForUser($user);
        $filter = EventTimeFilter::fromRequest($request->query->getString('vue'));

        $page = max(1, $request->query->getInt('page', 1));
        $totalItems = $this->eventRepository->countVisibleByFilter($memberGroupIds, $filter);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $events = $this->eventRepository->findVisibleByFilterPaginated($memberGroupIds, $filter, $page, self::PER_PAGE);
        $creatableGroups = $this->getCreatableGroups($user);

        return $this->render('events/index.html.twig', [
            'events' => $events,
            'current_filter' => $filter,
            'can_create' => [] !== $creatableGroups,
            'is_regular_member_only' => [] !== $memberGroupIds && [] === $creatableGroups,
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalItems,
                'per_page' => self::PER_PAGE,
            ],
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();
        $creatableGroups = $this->getCreatableGroups($user);

        if ([] === $creatableGroups) {
            $this->addWarningFlash('Seuls le chef ou le modérateur d\'un groupe peuvent publier un événement. Demande à ton chef ou modérateur.');

            return $this->redirectToRoute('app_events');
        }

        $preselectedGroup = null;
        $groupId = $request->query->getInt('groupe');
        if ($groupId > 0) {
            $preselectedGroup = $this->findGroupInList($groupId, $creatableGroups);
        }

        $event = new Event();
        $form = $this->createForm(EventFormType::class, $event, [
            'member_groups' => $creatableGroups,
            'preselected_group' => $preselectedGroup,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group = $event->getRelatedGroup();
            if (null === $group || !$this->eventAccess->canCreateInGroup($user, $group)) {
                throw $this->createAccessDeniedException();
            }

            $event->setAuthor($user);
            try {
                $this->handlePhotoUploads($form, $event);
            } catch (\DomainException $e) {
                $this->addErrorFlash($e->getMessage());

                return $this->render('events/new.html.twig', [
                    'eventForm' => $form,
                ]);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $this->addSuccessFlash('L\'événement a été publié.');

            return $this->redirectToRoute('app_events_show', ['id' => $event->getId()]);
        }

        return $this->render('events/new.html.twig', [
            'eventForm' => $form,
        ]);
    }

    #[Route('/{id}/modifier', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $event = $this->eventRepository->findOneWithRelations($id);
        if (null === $event) {
            throw $this->createNotFoundException();
        }

        if (!$this->eventAccess->canView($user, $event) || !$this->eventAccess->canEdit($user, $event)) {
            throw $this->createAccessDeniedException();
        }

        $creatableGroups = $this->getCreatableGroups($user);
        $form = $this->createForm(EventFormType::class, $event, [
            'member_groups' => $creatableGroups,
            'allow_remove_photo' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $group = $event->getRelatedGroup();
            if (null === $group || !$this->eventAccess->canCreateInGroup($user, $group)) {
                throw $this->createAccessDeniedException();
            }

            if ($form->has('removePhotoCover') && $form->get('removePhotoCover')->getData()) {
                $this->eventImageService->removePhoto($event, EventPhotoSlot::Cover);
            }
            if ($form->has('removePhotoDetail') && $form->get('removePhotoDetail')->getData()) {
                $this->eventImageService->removePhoto($event, EventPhotoSlot::Detail);
            }

            try {
                $this->handlePhotoUploads($form, $event);
            } catch (\DomainException $e) {
                $this->addErrorFlash($e->getMessage());

                return $this->render('events/edit.html.twig', [
                    'event' => $event,
                    'eventForm' => $form,
                ]);
            }

            $this->entityManager->flush();
            $this->addSuccessFlash('L\'événement a été mis à jour.');

            return $this->redirectToRoute('app_events_show', ['id' => $event->getId()]);
        }

        return $this->render('events/edit.html.twig', [
            'event' => $event,
            'eventForm' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: '_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $event = $this->eventRepository->findOneWithRelations($id);
        if (null === $event) {
            throw $this->createNotFoundException();
        }

        if (!$this->eventAccess->canDelete($user, $event)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete-event'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $redirectFilter = EventTimeFilter::fromRequest((string) $request->request->get('vue', 'upcoming'));
        $this->eventImageService->deleteEventFiles($event);
        $event->setPhotoCover(null);
        $event->setPhotoDetail(null);
        $this->entityManager->remove($event);
        $this->entityManager->flush();

        $this->addSuccessFlash('L\'événement a été supprimé.');

        return $this->redirectToRoute('app_events', ['vue' => $redirectFilter->value]);
    }

    #[Route('/{id}/contacter', name: '_contact_staff', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function contactStaff(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $event = $this->eventRepository->findOneWithRelations($id);
        if (null === $event) {
            throw $this->createNotFoundException();
        }

        if (!$this->eventAccess->canContactStaff($user, $event)) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('contact-event-staff'.$id, (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Réessaie.');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        if ('' !== trim((string) $request->request->get('website', ''))) {
            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $recipientId = (int) $request->request->get('recipient_id', 0);
        $content = trim((string) $request->request->get('content', ''));

        if (\strlen($content) < 15) {
            $this->addErrorFlash('Le message doit contenir au moins 15 caractères.');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        if (\strlen($content) > 1000) {
            $this->addErrorFlash('Le message ne peut pas dépasser 1000 caractères.');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $recipient = $this->userRepository->findActiveById($recipientId);
        if (null === $recipient || !$this->isGroupStaffRecipient($event, $recipient)) {
            $this->addErrorFlash('Destinataire invalide.');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $prefixedContent = sprintf(
            "Bonjour,\n\nJe souhaite proposer ou discuter d'un événement pour le groupe « %s ».\n\nÉvénement concerné : « %s »\n\n%s",
            $event->getRelatedGroup()?->getDisplayLabel() ?? '',
            $event->getTitle(),
            $content,
        );

        try {
            $this->messageService->sendPrivateMessage($user, $recipient, $prefixedContent);
            $this->addSuccessFlash('Message envoyé au responsable du groupe.');
        } catch (\DomainException $e) {
            $this->addErrorFlash($e->getMessage());
        }

        return $this->redirectToRoute('app_events_show', ['id' => $id]);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->requireUser();
        $event = $this->eventRepository->findOneWithRelations($id);
        if (null === $event) {
            throw $this->createNotFoundException();
        }

        if (!$this->eventAccess->canView($user, $event)) {
            throw $this->createAccessDeniedException();
        }

        $group = $event->getRelatedGroup();
        $staffContacts = null !== $group ? $this->collectGroupStaffContacts($group) : [];

        return $this->render('events/show.html.twig', [
            'event' => $event,
            'can_edit' => $this->eventAccess->canEdit($user, $event),
            'can_delete' => $this->eventAccess->canDelete($user, $event),
            'can_contact_staff' => $this->eventAccess->canContactStaff($user, $event),
            'staff_contacts' => $staffContacts,
            'is_past' => $event->isPast(ParisClock::now()),
        ]);
    }

    /**
     * @return list<Group>
     */
    private function getCreatableGroups(User $user): array
    {
        if ($this->siteStaff->isSiteStaff($user)) {
            return $this->groupMemberRepository->findGroupsForUser($user);
        }

        return $this->groupMemberRepository->findStaffGroupsForUser($user);
    }

    /**
     * @param list<Group> $groups
     */
    private function findGroupInList(int $groupId, array $groups): ?Group
    {
        foreach ($groups as $group) {
            if ($group->getId() === $groupId) {
                return $group;
            }
        }

        return null;
    }

    private function handlePhotoUploads(\Symfony\Component\Form\FormInterface $form, Event $event): void
    {
        $slots = [
            'photoCoverFile' => EventPhotoSlot::Cover,
            'photoDetailFile' => EventPhotoSlot::Detail,
        ];

        foreach ($slots as $field => $slot) {
            if (!$form->has($field)) {
                continue;
            }

            $uploadedFile = $form->get($field)->getData();
            if (!$uploadedFile instanceof UploadedFile) {
                continue;
            }

            try {
                $this->eventImageService->storeUploadedPhoto($event, $uploadedFile, $slot);
            } catch (\InvalidArgumentException $e) {
                throw new \DomainException($e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * @return list<array{user: User, label: string}>
     */
    private function collectGroupStaffContacts(Group $group): array
    {
        $contacts = [];
        $owner = $group->getOwner();
        if (null !== $owner) {
            $contacts[$owner->getId() ?? 0] = ['user' => $owner, 'label' => 'Chef du groupe'];
        }

        foreach ($group->getGroupMembers() as $member) {
            if (GroupMemberRole::Moderator !== $member->getRole()) {
                continue;
            }
            $memberUser = $member->getUser();
            if (null === $memberUser) {
                continue;
            }
            $contacts[$memberUser->getId() ?? 0] = ['user' => $memberUser, 'label' => 'Modérateur'];
        }

        return array_values($contacts);
    }

    private function isGroupStaffRecipient(Event $event, User $recipient): bool
    {
        $group = $event->getRelatedGroup();
        if (null === $group) {
            return false;
        }

        foreach ($this->collectGroupStaffContacts($group) as $contact) {
            if ($contact['user']->getId() === $recipient->getId()) {
                return true;
            }
        }

        return false;
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
