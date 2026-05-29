<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\GroupRequest;
use App\Entity\User;
use App\Enum\GroupRequestStatus;
use App\Util\ParisClock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupRequest>
 */
class GroupRequestRepository extends ServiceEntityRepository
{
    public const int MAX_REQUESTS_AFTER_REFUSAL = 3;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupRequest::class);
    }

    public function findPendingForUserAndGroup(User $user, Group $group): ?GroupRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countRefusedForUserAndGroup(User $user, Group $group): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Refused)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function canUserRequestAgain(User $user, Group $group): bool
    {
        if (null !== $this->findPendingForUserAndGroup($user, $group)) {
            return false;
        }

        return $this->countRefusedForUserAndGroup($user, $group) < self::MAX_REQUESTS_AFTER_REFUSAL;
    }

    /**
     * @return list<GroupRequest>
     */
    public function findPendingByGroup(Group $group): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadPendingByGroup(Group $group): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->andWhere('r.readAt IS NULL')
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findInvitedForUserAndGroup(User $user, Group $group): ?GroupRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Invited)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneForGroup(int $requestId, Group $group): ?GroupRequest
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.id = :id')
            ->andWhere('r.relatedGroup = :group')
            ->setParameter('id', $requestId)
            ->setParameter('group', $group)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<GroupRequest>
     */
    public function findPendingByGroupWithUser(Group $group): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('u')
            ->innerJoin('r.user', 'u')
            ->andWhere('r.relatedGroup = :group')
            ->andWhere('r.status = :status')
            ->setParameter('group', $group)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $userIds
     *
     * @return array<int, array{pending_or_invited: bool, refused_count: int}>
     */
    public function buildInviteStatusMap(Group $group, array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $map = [];
        foreach ($userIds as $userId) {
            $user = $this->getEntityManager()->getReference(User::class, $userId);
            $map[$userId] = [
                'pending_or_invited' => null !== $this->findPendingForUserAndGroup($user, $group)
                    || null !== $this->findInvitedForUserAndGroup($user, $group),
                'refused_count' => $this->countRefusedForUserAndGroup($user, $group),
            ];
        }

        return $map;
    }

    /**
     * @return list<GroupRequest>
     */
    public function findInvitedForUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('g')
            ->innerJoin('r.relatedGroup', 'g')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', GroupRequestStatus::Invited)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $groupIds
     *
     * @return list<GroupRequest>
     */
    public function findPendingForGroups(array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->addSelect('u', 'g')
            ->innerJoin('r.user', 'u')
            ->innerJoin('r.relatedGroup', 'g')
            ->andWhere('r.relatedGroup IN (:groups)')
            ->andWhere('r.status = :status')
            ->setParameter('groups', $groupIds)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadInvitationsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.readAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('status', GroupRequestStatus::Invited)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $groupIds
     */
    public function countUnreadPendingForGroups(array $groupIds): int
    {
        if ([] === $groupIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.relatedGroup IN (:groups)')
            ->andWhere('r.status = :status')
            ->andWhere('r.readAt IS NULL')
            ->setParameter('groups', $groupIds)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markInvitationsAsReadForUser(User $user): void
    {
        $this->createQueryBuilder('r')
            ->update()
            ->set('r.readAt', ':now')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->andWhere('r.readAt IS NULL')
            ->setParameter('now', ParisClock::now())
            ->setParameter('user', $user)
            ->setParameter('status', GroupRequestStatus::Invited)
            ->getQuery()
            ->execute();
    }

    /**
     * @param list<int> $groupIds
     */
    public function markPendingAsReadForGroups(array $groupIds): void
    {
        if ([] === $groupIds) {
            return;
        }

        $this->createQueryBuilder('r')
            ->update()
            ->set('r.readAt', ':now')
            ->andWhere('r.relatedGroup IN (:groups)')
            ->andWhere('r.status = :status')
            ->andWhere('r.readAt IS NULL')
            ->setParameter('now', ParisClock::now())
            ->setParameter('groups', $groupIds)
            ->setParameter('status', GroupRequestStatus::Pending)
            ->getQuery()
            ->execute();
    }
}
