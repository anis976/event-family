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
use App\Util\Pagination;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/groupes', name: 'app_groups')]
#[IsGranted('ROLE_USER')]
final class GroupController extends AbstractAppController
{
    private const GROUPS_PER_PAGE = 9;

    /** Membres affichés par page sur la fiche groupe (ajustable). */
    private const MEMBERS_PER_PAGE = 12;

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
        $memberGroupIds = $this->groupMemberRepository->findGroupIdsForUser($user);

        $myGroupsTotal = $this->groupMemberRepository->countGroupsForUser($user);
        $myGroupsPagination = Pagination::create(
            $request->query->getInt('page_my', 1),
            $myGroupsTotal,
            self::GROUPS_PER_PAGE,
        );
        $myGroups = $this->groupMemberRepository->findGroupsForUserPaginated(
            $user,
            $myGroupsPagination['page'],
            self::GROUPS_PER_PAGE,
        );

        $totalOthers = $this->groupRepository->countOthers($memberGroupIds);
        $othersPagination = Pagination::create(
            $request->query->getInt('page', 1),
            $totalOthers,
            self::GROUPS_PER_PAGE,
        );
        $otherGroups = $this->groupRepository->findOthersPaginated(
            $memberGroupIds,
            $othersPagination['page'],
            self::GROUPS_PER_PAGE,
        );

        return $this->render('groups/index.html.twig', [
            'myGroups' => $myGroups,
            'myGroupsPagination' => $myGroupsPagination,
            'otherGroups' => $otherGroups,
            'member_counts' => $this->buildMemberCountsMap([...$myGroups, ...$otherGroups]),
            'memberGroupIds' => array_flip($memberGroupIds),
            'can_create' => $this->groupAccess->canCreateGroup($user),
            'othersPagination' => $othersPagination,
        ]);
    }

    #[Route('/nouveau', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->requireUser();

        if (!$this->groupAccess->canCreateGroup($user)) {
            $this->addErrorFlash('flash.group.create_limit');

            return $this->redirectToRoute('app_groups');
        }

        $group = new Group();
        $form = $this->createForm(GroupFormType::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->groupRepository->existsByFamilyName($group->getFamilyName())) {
                $this->addWarningFlash('flash.group.family_name_exists', [
                    '%family%' => $group->getFamilyName(),
                ]);
            }

            $group->setAuthor($user);
            $group->setOwner($user);

            $ownerMembership = (new GroupMember())
                ->setUser($user)
                ->setRole(GroupMemberRole::Owner);
            $group->addGroupMember($ownerMembership);

            $this->entityManager->persist($group);
            $this->entityManager->flush();

            $this->addSuccessFlash('flash.group.created');

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
            $this->addSuccessFlash('flash.group.updated');

            return $this->redirectToRoute('app_groups_show', ['id' => $group->getId()]);
        }

        return $this->render('groups/edit.html.twig', [
            'group' => $group,
            'groupForm' => $form,
        ]);
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request): Response
    {
        $user = $this->requireUser();
        $group = $this->groupRepository->findOneForShow($id);
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

        $membersTotal = $this->groupMemberRepository->countByGroup($group);
        $membersPagination = Pagination::create($request->query->getInt('page', 1), $membersTotal, self::MEMBERS_PER_PAGE);
        $groupMembers = $this->groupMemberRepository->findByGroupPaginated(
            $group,
            $membersPagination['page'],
            self::MEMBERS_PER_PAGE,
        );

        return $this->render('groups/show.html.twig', [
            'group' => $group,
            'groupMembers' => $groupMembers,
            'membersTotal' => $membersTotal,
            'membersPagination' => $membersPagination,
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
            'groupEventsUpcoming' => $isMember ? $this->eventRepository->findByGroupAndFilter($group, EventTimeFilter::Upcoming) : [],
            'groupEventsOngoing' => $isMember ? $this->eventRepository->findByGroupAndFilter($group, EventTimeFilter::Ongoing) : [],
            'can_create_event' => $isMember && $this->eventAccess->canCreateInGroup($user, $group),
            'is_regular_member' => $isMember && !$this->eventAccess->canCreateInGroup($user, $group),
        ]);
    }

    /**
     * @param list<Group> $groups
     *
     * @return array<int, int>
     */
    private function buildMemberCountsMap(array $groups): array
    {
        $ids = [];
        foreach ($groups as $group) {
            $id = $group->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $this->groupMemberRepository->countMembersByGroupIds($ids);
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
