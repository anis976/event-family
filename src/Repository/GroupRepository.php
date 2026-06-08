<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Entity\GroupMember;
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
     * Groupes dont l'utilisateur est responsable et qui ont au moins un autre membre.
     *
     * @return list<Group>
     */
    public function findOwnedGroupsWithOtherMembers(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :user')
            ->andWhere(
                '(SELECT COUNT(gm.id) FROM '.GroupMember::class.' gm WHERE gm.group = g AND gm.user != :user) > 0',
            )
            ->setParameter('user', $user)
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Group>
     */
    public function findOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.owner = :user')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $excludeGroupIds
     *
     * @return list<Group>
     */
    public function findOthersPaginated(array $excludeGroupIds, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('g')
            ->addSelect('owner')
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

    /** Contexte messagerie de groupe (sans membres ni jointures lourdes). */
    public function findOneForGroupMessages(int $id): ?Group
    {
        return $this->find($id);
    }

    /** Fiche groupe sans charger tous les membres (liste paginée à part). */
    public function findOneForShow(int $id): ?Group
    {
        return $this->createQueryBuilder('g')
            ->addSelect('owner', 'author')
            ->leftJoin('g.owner', 'owner')
            ->leftJoin('g.author', 'author')
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

    public function usersShareAtLeastOneGroup(User $first, User $second): bool
    {
        if ($first->getId() === $second->getId()) {
            return true;
        }

        $firstGroupIds = $this->resolveGroupIdsForUser($first);
        $secondGroupIds = $this->resolveGroupIdsForUser($second);

        return [] !== array_intersect($firstGroupIds, $secondGroupIds);
    }

    /**
     * @return list<int>
     */
    private function resolveGroupIdsForUser(User $user): array
    {
        $memberIds = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(gm.group)')
            ->from(\App\Entity\GroupMember::class, 'gm')
            ->andWhere('gm.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        $ownedIds = $this->createQueryBuilder('g')
            ->select('g.id')
            ->andWhere('g.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_unique(array_map('intval', [...$memberIds, ...$ownedIds])));
    }
}
