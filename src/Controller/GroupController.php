<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use App\Form\GroupFormType;
use App\Enum\EventTimeFilter;
use App\Repository\EventRepository;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\GroupRequestRepository;
use App\Repository\UserBanRepository;
use App\Service\EventAccessService;
use App\Service\GroupAccessService;
use App\Service\GroupRequestService;
use App\Service\SiteStaffService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/groupes', name: 'app_groups')]
#[IsGranted('ROLE_USER')]
final class GroupController extends AbstractAppController
{
    private const OTHERS_PER_PAGE = 9;

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly EventRepository $eventRepository,
        private readonly GroupAccessService $groupAccess,
        private readonly EventAccessService $eventAccess,
        private readonly GroupRequestRepository $groupRequestRepository,
        private readonly GroupRequestService $groupRequestService,
        private readonly UserBanRepository $userBanRepository,
        private readonly SiteStaffService $siteStaff,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $myGroups = $this->groupMemberRepository->findGroupsForUser($user);
        $memberGroupIds = array_flip($this->groupMemberRepository->findGroupIdsForUser($user));

        $page = max(1, $request->query->getInt('page', 1));
        $totalOthers = $this->groupRepository->countOthers(array_keys($memberGroupIds));
        $totalPages = max(1, (int) ceil($totalOthers / self::OTHERS_PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $otherGroups = $this->groupRepository->findOthersPaginated(array_keys($memberGroupIds), $page, self::OTHERS_PER_PAGE);

        return $this->render('groups/index.html.twig', [
            'myGroups' => $myGroups,
            'otherGroups' => $otherGroups,
            'memberGroupIds' => $memberGroupIds,
            'can_create' => $this->groupAccess->canCreateGroup($user),
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalOthers,
                'per_page' => self::OTHERS_PER_PAGE,
            ],
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();

        if (!$this->groupAccess->canCreateGroup($user)) {
            $this->addErrorFlash('Tu as déjà créé un groupe. Un seul groupe par compte est autorisé.');

            return $this->redirectToRoute('app_groups');
        }

        $group = new Group();
        $form = $this->createForm(GroupFormType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->groupRepository->existsByFamilyName($group->getFamilyName())) {
                $this->addWarningFlash(sprintf(
                    'Un groupe représente déjà la famille « %s ». Tu peux quand même créer le tien.',
                    $group->getFamilyName(),
                ));
            }

            $group->setAuthor($user);
            $group->setOwner($user);

            $ownerMembership = (new GroupMember())
                ->setUser($user)
                ->setRole(GroupMemberRole::Owner);
            $group->addGroupMember($ownerMembership);

            $this->entityManager->persist($group);
            $this->entityManager->flush();

            $this->addSuccessFlash('Ton groupe a été créé avec succès.');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        return $this->render('groups/new.html.twig', [
            'groupForm' => $form,
        ]);
    }

    #[Route('/{id}/apercu', name: '_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id): Response
    {
        return $this->redirectToRoute('app_groups_show', ['id' => $id]);
    }

    #[Route('/{id}/modifier', name: '_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $group = $this->groupRepository->findOneWithMembers($id);
        if (null === $group) {
            throw $this->createNotFoundException();
        }

        if (!$this->groupAccess->isOwner($user, $group)) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(GroupFormType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addSuccessFlash('Les informations du groupe ont été mises à jour.');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        return $this->render('groups/edit.html.twig', [
            'group' => $group,
            'groupForm' => $form,
        ]);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $user = $this->requireUser();
        $group = $this->groupRepository->findOneWithMembers($id);
        if (null === $group) {
            throw $this->createNotFoundException();
        }

        $isMember = $this->groupAccess->isMember($user, $group);
        $isOwner = $isMember && $this->groupAccess->isOwner($user, $group);
        $currentMember = $isMember ? $this->groupAccess->findMembership($user, $group) : null;
        $isModerator = $isMember && $this->groupAccess->isModerator($user, $group);
        $bannedUserIds = $isMember
            ? array_flip($this->userBanRepository->findActiveBannedUserIdsForGroup($group))
            : [];

        $joinState = $this->groupRequestService->getVisitorJoinState($user, $group);
        $isStaff = $isMember && $this->groupAccess->isStaff($user, $group);
        $pendingRequestsCount = $isStaff
            ? $this->groupRequestRepository->countUnreadPendingByGroup($group)
            : 0;

        return $this->render('groups/show.html.twig', [
            'group' => $group,
            'isMember' => $isMember,
            'isOwner' => $isOwner,
            'isChef' => $isOwner,
            'isModerator' => $isModerator,
            'isStaff' => $isStaff,
            'isSiteStaff' => $this->siteStaff->isSiteStaff($user),
            'currentMember' => $currentMember,
            'bannedUserIds' => $bannedUserIds,
            'joinState' => $joinState,
            'pendingRequestsCount' => $pendingRequestsCount,
            'groupEvents' => $isMember ? $this->eventRepository->findByGroupAndFilter($group, EventTimeFilter::Upcoming) : [],
            'can_create_event' => $isMember && $this->eventAccess->canCreateInGroup($user, $group),
            'is_regular_member' => $isMember && !$this->eventAccess->canCreateInGroup($user, $group),
        ]);
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
