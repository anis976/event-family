<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    public function countOwnedByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('g.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $excludeGroupIds
     *
     * @return list<Group>
     */
    public function findOthersPaginated(array $excludeGroupIds, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('g')
            ->addSelect('gm', 'u', 'owner')
            ->leftJoin('g.groupMembers', 'gm')
            ->leftJoin('gm.user', 'u')
            ->leftJoin('g.owner', 'owner')
            ->orderBy('g.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        if ([] !== $excludeGroupIds) {
            $qb->andWhere('g.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeGroupIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int> $excludeGroupIds
     */
    public function countOthers(array $excludeGroupIds): int
    {
        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)');

        if ([] !== $excludeGroupIds) {
            $qb->andWhere('g.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeGroupIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOneWithMembers(int $id): ?Group
    {
        return $this->createQueryBuilder('g')
            ->addSelect('gm', 'u', 'owner')
            ->leftJoin('g.groupMembers', 'gm')
            ->leftJoin('gm.user', 'u')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByFamilyName(string $familyName, ?int $excludeGroupId = null): bool
    {
        $normalized = mb_strtolower(trim($familyName));
        if ('' === $normalized) {
            return false;
        }

        $qb = $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->andWhere('LOWER(g.familyName) = :familyName')
            ->setParameter('familyName', $normalized);

        if (null !== $excludeGroupId) {
            $qb->andWhere('g.id != :excludeId')
                ->setParameter('excludeId', $excludeGroupId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
