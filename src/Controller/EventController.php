<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Group;
use App\Entity\User;
use App\Enum\EventTimeFilter;
use App\Enum\EventPhotoSlot;
use App\Enum\EventVisibility;
use App\Enum\GroupMemberRole;
use App\Form\EventFormType;
use App\Repository\EventRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\UserRepository;
use App\Service\EventAccessService;
use App\Service\EventImageService;
use App\Service\MessageService;
use App\Service\SiteStaffService;
use App\Service\StaffCircleService;
use App\Util\Pagination;
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
        private readonly StaffCircleService $staffCircleService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $memberGroupIds = $this->groupMemberRepository->findGroupIdsForUser($user);
        $filter = EventTimeFilter::fromRequest($request->query->getString('vue'));
        $searchQuery = $this->normalizeSearchQuery($request->query->getString('q'));

        $totalItems = $this->eventRepository->countVisibleByFilter($memberGroupIds, $filter, $searchQuery);
        $pagination = Pagination::create($request->query->getInt('page', 1), $totalItems, self::PER_PAGE);
        $events = $this->eventRepository->findVisibleByFilterPaginated(
            $memberGroupIds,
            $filter,
            $pagination['page'],
            self::PER_PAGE,
            $searchQuery,
        );
        $creatableGroups = $this->getCreatableGroups($user);

        return $this->render('events/index.html.twig', [
            'events' => $events,
            'current_filter' => $filter,
            'event_filters' => EventTimeFilter::cases(),
            'search_query' => $searchQuery ?? '',
            'can_create' => [] !== $creatableGroups,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();
        $creatableGroups = $this->getCreatableGroups($user);

        if ([] === $creatableGroups) {
            $this->addWarningFlash('flash.event.publish_staff_only');

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
            'show_staff_circle_share' => true,
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

            $wantsStaffCircleShare = $form->has('sharedInStaffCircle')
                && (bool) $form->get('sharedInStaffCircle')->getData();
            $this->staffCircleService->applyEventStaffCircleSharing($event, $wantsStaffCircleShare);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            $this->addSuccessFlash('flash.event.published');
            if ($event->isVisibleInStaffCircle()) {
                $this->addSuccessFlash('flash.event.shared_in_staff_circle');
            }

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
            'show_staff_circle_share' => !$event->getRelatedGroup()?->isStaffCircle(),
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

            $wantsStaffCircleShare = $form->has('sharedInStaffCircle')
                ? (bool) $form->get('sharedInStaffCircle')->getData()
                : $event->isSharedInStaffCircle();
            $this->staffCircleService->applyEventStaffCircleSharing($event, $wantsStaffCircleShare);

            $this->entityManager->flush();
            $this->addSuccessFlash('flash.event.updated');

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

        $this->addSuccessFlash('flash.event.deleted');

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
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        if ('' !== trim((string) $request->request->get('website', ''))) {
            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $recipientId = (int) $request->request->get('recipient_id', 0);
        $content = trim((string) $request->request->get('content', ''));

        if (\strlen($content) < 15) {
            $this->addErrorFlash('flash.event.contact_min_length');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        if (\strlen($content) > 1000) {
            $this->addErrorFlash('flash.event.contact_max_length');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $recipient = $this->userRepository->findActiveById($recipientId);
        if (null === $recipient || !$this->isGroupStaffRecipient($event, $recipient)) {
            $this->addErrorFlash('flash.event.contact_invalid_recipient');

            return $this->redirectToRoute('app_events_show', ['id' => $id]);
        }

        $prefixedContent = $this->trans('event.contact.message_body', [
            '%group%' => $event->getRelatedGroup()?->getDisplayLabel() ?? '',
            '%title%' => $event->getTitle(),
            '%content%' => $content,
        ]);

        try {
            $this->messageService->sendPrivateMessage($user, $recipient, $prefixedContent);
            $this->addSuccessFlash('flash.event.contact_sent');
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
            return $this->filterCreatableGroups($this->groupMemberRepository->findGroupsForUser($user));
        }

        return $this->filterCreatableGroups($this->groupMemberRepository->findStaffGroupsForUser($user));
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

    /**
     * @param list<Group> $groups
     *
     * @return list<Group>
     */
    private function filterCreatableGroups(array $groups): array
    {
        return array_values(array_filter(
            $groups,
            static fn (Group $group): bool => !$group->isStaffCircle(),
        ));
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
            $contacts[$owner->getId() ?? 0] = ['user' => $owner, 'label' => 'ui.events.show.staff_contact_owner'];
        }

        foreach ($group->getGroupMembers() as $member) {
            if (GroupMemberRole::Moderator !== $member->getRole()) {
                continue;
            }
            $memberUser = $member->getUser();
            if (null === $memberUser) {
                continue;
            }
            $contacts[$memberUser->getId() ?? 0] = ['user' => $memberUser, 'label' => 'ui.events.show.staff_contact_moderator'];
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

    private function normalizeSearchQuery(string $raw): ?string
    {
        $query = trim($raw);
        if ('' === $query) {
            return null;
        }

        if (\strlen($query) > 100) {
            $query = substr($query, 0, 100);
        }

        return $query;
    }
}
