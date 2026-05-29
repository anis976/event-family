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
        return $this->getEntityManager()->createQueryBuilder()
            ->select('g', 'allGm', 'memberUser', 'owner')
            ->from(Group::class, 'g')
            ->innerJoin('g.groupMembers', 'gm')
            ->leftJoin('g.groupMembers', 'allGm')
            ->leftJoin('allGm.user', 'memberUser')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('gm.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
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
     * @return list<int>
     */
    public function findStaffGroupIdsForUser(User $user): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT g.id AS groupId')
            ->from(Group::class, 'g')
            ->leftJoin('g.groupMembers', 'gm', 'WITH', 'gm.user = :user')
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
}
