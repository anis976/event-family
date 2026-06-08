<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserBan;
use App\Util\ParisClock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBan>
 */
class UserBanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBan::class);
    }

    public function countTotalBansForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.bannedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBansForUserAtOrBefore(User $user, \DateTimeImmutable $at): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.bannedUser = :user')
            ->andWhere('b.createdAt <= :at')
            ->setParameter('user', $user)
            ->setParameter('at', $at)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestActivePlatformBanForUser(User $user): ?UserBan
    {
        $now = ParisClock::now();

        return $this->createQueryBuilder('b')
            ->andWhere('b.bannedUser = :user')
            ->andWhere('b.relatedGroup IS NULL')
            ->andWhere('b.endsAt IS NULL OR b.endsAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasActivePlatformBan(User $user): bool
    {
        return null !== $this->findLatestActivePlatformBanForUser($user);
    }

    /**
     * @return list<UserBan>
     */
    public function findActivePlatformBansForUser(User $user): array
    {
        $now = ParisClock::now();

        return $this->createQueryBuilder('b')
            ->andWhere('b.bannedUser = :user')
            ->andWhere('b.relatedGroup IS NULL')
            ->andWhere('b.endsAt IS NULL OR b.endsAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveBanForUserInGroup(User $user, Group $group): ?UserBan
    {
        $now = ParisClock::now();

        return $this->createQueryBuilder('b')
            ->andWhere('b.bannedUser = :user')
            ->andWhere('b.relatedGroup = :group')
            ->andWhere('b.endsAt IS NULL OR b.endsAt > :now')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->setParameter('now', $now)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<UserBan>
     */
    public function findActiveBansForUser(User $user): array
    {
        $now = ParisClock::now();

        return $this->createQueryBuilder('b')
            ->andWhere('b.bannedUser = :user')
            ->andWhere('b.endsAt IS NULL OR b.endsAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findActiveBannedUserIdsForGroup(Group $group): array
    {
        $now = ParisClock::now();
        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.bannedUser) AS userId')
            ->andWhere('b.relatedGroup = :group')
            ->andWhere('b.endsAt IS NULL OR b.endsAt > :now')
            ->setParameter('group', $group)
            ->setParameter('now', $now)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['userId'], $rows);
    }
}
