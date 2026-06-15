<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupMember>
 */
class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    public function findOneByUserAndGroup(User $user, Group $group): ?GroupMember
    {
        return $this->findOneBy(['user' => $user, 'group' => $group]);
    }

    /**
     * @return list<Group>
     */
    public function findGroupsForUser(User $user): array
    {
        return $this->findGroupsForUserPaginated($user, 1, PHP_INT_MAX);
    }

    /**
     * Liste légère pour le sélecteur de groupe (messages de groupe).
     *
     * @return list<array{id: int, name: string}>
     */
    public function findGroupPickerForUser(User $user): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('g.id', 'g.name')
            ->from(Group::class, 'g')
            ->innerJoin('g.groupMembers', 'gm')
            ->andWhere('gm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('CASE WHEN g.owner = :user THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
            $rows,
        );
    }

    public function countGroupsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('gm')
            ->select('COUNT(DISTINCT g.id)')
            ->innerJoin('gm.group', 'g')
            ->andWhere('gm.user = :user')
            ->andWhere('g.isStaffCircle = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Group>
     */
    public function findGroupsForUserPaginated(User $user, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return $this->getEntityManager()->createQueryBuilder()
            ->select('g', 'owner')
            ->from(Group::class, 'g')
            ->innerJoin('g.groupMembers', 'gm')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('gm.user = :user')
            ->andWhere('g.isStaffCircle = false')
            ->setParameter('user', $user)
            ->orderBy('CASE WHEN g.owner = :user THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('g.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findAllStaffUserIdsExcludingStaffCircle(): array
    {
        $ownerIds = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(g.owner) AS userId')
            ->from(Group::class, 'g')
            ->andWhere('g.isStaffCircle = false')
            ->andWhere('g.owner IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        $moderatorIds = $this->createQueryBuilder('gm')
            ->select('DISTINCT IDENTITY(gm.user) AS userId')
            ->innerJoin('gm.group', 'g')
            ->andWhere('g.isStaffCircle = false')
            ->andWhere('gm.role = :moderatorRole')
            ->setParameter('moderatorRole', GroupMemberRole::Moderator)
            ->getQuery()
            ->getSingleColumnResult();

        $ids = array_map('intval', [...$ownerIds, ...$moderatorIds]);

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return list<int>
     */
    public function findUserIdsInGroup(Group $group): array
    {
        $rows = $this->createQueryBuilder('gm')
            ->select('IDENTITY(gm.user) AS userId')
            ->andWhere('gm.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['userId'], $rows);
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, int> groupId => member count
     */
    public function countMembersByGroupIds(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('gm')
            ->select('IDENTITY(gm.group) AS groupId', 'COUNT(gm.id) AS memberCount')
            ->andWhere('gm.group IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->groupBy('gm.group')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['groupId']] = (int) $row['memberCount'];
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    public function findGroupIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('gm')
            ->select('IDENTITY(gm.group) AS groupId')
            ->andWhere('gm.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['groupId'], $rows);
    }

    public function countModeratorsInGroup(Group $group): int
    {
        return (int) $this->createQueryBuilder('gm')
            ->select('COUNT(gm.id)')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.role = :role')
            ->setParameter('group', $group)
            ->setParameter('role', \App\Enum\GroupMemberRole::Moderator)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<GroupMember>
     */
    public function findModeratorsInGroup(Group $group): array
    {
        return $this->createQueryBuilder('gm')
            ->addSelect('u')
            ->innerJoin('gm.user', 'u')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.role = :role')
            ->setParameter('group', $group)
            ->setParameter('role', GroupMemberRole::Moderator)
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<GroupMember>
     */
    public function findModeratorsInGroupExcluding(Group $group, User $excludeUser): array
    {
        return $this->createQueryBuilder('gm')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.role = :role')
            ->andWhere('gm.user != :user')
            ->setParameter('group', $group)
            ->setParameter('role', GroupMemberRole::Moderator)
            ->setParameter('user', $excludeUser)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<GroupMember>
     */
    public function findOwnerMembersInGroupExcluding(Group $group, User $excludeUser): array
    {
        return $this->createQueryBuilder('gm')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.role = :role')
            ->andWhere('gm.user != :user')
            ->setParameter('group', $group)
            ->setParameter('role', GroupMemberRole::Owner)
            ->setParameter('user', $excludeUser)
            ->getQuery()
            ->getResult();
    }

    public function countOtherMembersInGroup(Group $group, User $excludeUser): int
    {
        return (int) $this->createQueryBuilder('gm')
            ->select('COUNT(gm.id)')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.user != :user')
            ->setParameter('group', $group)
            ->setParameter('user', $excludeUser)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Autres membres du groupe (hors utilisateur exclu), modérateur en priorité puis ancienneté.
     *
     * @return list<GroupMember>
     */
    public function findOtherMembersOrdered(Group $group, User $excludeUser): array
    {
        return $this->createQueryBuilder('gm')
            ->addSelect('u')
            ->innerJoin('gm.user', 'u')
            ->andWhere('gm.group = :group')
            ->andWhere('gm.user != :user')
            ->setParameter('group', $group)
            ->setParameter('user', $excludeUser)
            ->addOrderBy('CASE WHEN gm.role = :moderatorRole THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('gm.joinedAt', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setParameter('moderatorRole', GroupMemberRole::Moderator->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findStaffGroupIdsForUser(User $user): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT g.id AS groupId')
            ->from(Group::class, 'g')
            ->leftJoin('g.groupMembers', 'gm', 'WITH', 'gm.user = :user')
            ->andWhere('g.isStaffCircle = false')
            ->andWhere('g.owner = :user OR gm.role = :moderatorRole')
            ->setParameter('user', $user)
            ->setParameter('moderatorRole', GroupMemberRole::Moderator)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['groupId'], $rows);
    }

    public function isStaffInAnyGroup(User $user): bool
    {
        return [] !== $this->findStaffGroupIdsForUser($user);
    }

    /**
     * @return list<Group>
     */
    public function findStaffGroupsForUser(User $user): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('g', 'allGm', 'memberUser', 'owner')
            ->from(Group::class, 'g')
            ->innerJoin('g.groupMembers', 'gm')
            ->leftJoin('g.groupMembers', 'allGm')
            ->leftJoin('allGm.user', 'memberUser')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('gm.user = :user')
            ->andWhere('g.isStaffCircle = false')
            ->andWhere('g.owner = :user OR gm.role IN (:staffRoles)')
            ->setParameter('user', $user)
            ->setParameter('staffRoles', [GroupMemberRole::Owner, GroupMemberRole::Moderator])
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithGroupAndUser(int $id): ?GroupMember
    {
        return $this->createQueryBuilder('gm')
            ->addSelect('g', 'u', 'owner')
            ->innerJoin('gm.group', 'g')
            ->innerJoin('gm.user', 'u')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('gm.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByGroup(Group $group): int
    {
        return (int) $this->createQueryBuilder('gm')
            ->select('COUNT(gm.id)')
            ->andWhere('gm.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Chef, puis modérateur, puis membres ; tri par nom dans chaque rôle.
     *
     * @return list<GroupMember>
     */
    public function findByGroupPaginated(Group $group, int $page, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return $this->createQueryBuilder('gm')
            ->addSelect('u')
            ->innerJoin('gm.user', 'u')
            ->andWhere('gm.group = :group')
            ->setParameter('group', $group)
            ->addOrderBy('CASE WHEN gm.role = :ownerRole THEN 0 WHEN gm.role = :moderatorRole THEN 1 ELSE 2 END', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setParameter('ownerRole', GroupMemberRole::Owner->value)
            ->setParameter('moderatorRole', GroupMemberRole::Moderator->value)
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les membres d'un groupe (fiche admin), triés par rôle puis nom.
     *
     * @return list<GroupMember>
     */
    public function findAllByGroupOrdered(Group $group): array
    {
        return $this->createQueryBuilder('gm')
            ->addSelect('u')
            ->innerJoin('gm.user', 'u')
            ->andWhere('gm.group = :group')
            ->setParameter('group', $group)
            ->addOrderBy('CASE WHEN gm.role = :ownerRole THEN 0 WHEN gm.role = :moderatorRole THEN 1 ELSE 2 END', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setParameter('ownerRole', GroupMemberRole::Owner->value)
            ->setParameter('moderatorRole', GroupMemberRole::Moderator->value)
            ->getQuery()
            ->getResult();
    }
}
