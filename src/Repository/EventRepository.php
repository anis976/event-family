<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Group;
use App\Enum\EventTimeFilter;
use App\Enum\EventVisibility;
use App\Util\ParisClock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findOneWithRelations(int $id): ?Event
    {
        return $this->createQueryBuilder('e')
            ->addSelect('g', 'author', 'owner')
            ->innerJoin('e.relatedGroup', 'g')
            ->leftJoin('e.author', 'author')
            ->leftJoin('g.owner', 'owner')
            ->andWhere('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $memberGroupIds
     */
    private function applyVisibleToUserFilter(QueryBuilder $qb, array $memberGroupIds, string $alias = 'e'): void
    {
        if ([] === $memberGroupIds) {
            $qb->andWhere(sprintf('%s.visibility = :publicVisibility', $alias))
                ->setParameter('publicVisibility', EventVisibility::Public);

            return;
        }

        $qb->andWhere(sprintf(
            '%s.visibility = :publicVisibility OR (%s.visibility = :groupVisibility AND %s.relatedGroup IN (:memberGroups))',
            $alias,
            $alias,
            $alias,
        ))
            ->setParameter('publicVisibility', EventVisibility::Public)
            ->setParameter('groupVisibility', EventVisibility::Group)
            ->setParameter('memberGroups', $memberGroupIds);
    }

    private function applyTimeFilter(QueryBuilder $qb, EventTimeFilter $filter, \DateTimeImmutable $now, string $alias = 'e'): void
    {
        $todayStart = $now->setTime(0, 0, 0);

        match ($filter) {
            EventTimeFilter::Upcoming => $qb
                ->andWhere(sprintf('%s.startDate > :eventNow', $alias))
                ->setParameter('eventNow', $now),
            EventTimeFilter::Ongoing => $qb
                ->andWhere(sprintf('%s.startDate <= :eventNow', $alias))
                ->andWhere(sprintf('(%s.endDate >= :eventNow OR (%s.endDate IS NULL AND %s.startDate >= :eventTodayStart))', $alias, $alias, $alias))
                ->setParameter('eventNow', $now)
                ->setParameter('eventTodayStart', $todayStart),
            EventTimeFilter::Past => $qb
                ->andWhere(sprintf(
                    '(%s.endDate IS NOT NULL AND %s.endDate < :eventNow) OR (%s.endDate IS NULL AND %s.startDate < :eventTodayStart)',
                    $alias,
                    $alias,
                    $alias,
                    $alias,
                ))
                ->setParameter('eventNow', $now)
                ->setParameter('eventTodayStart', $todayStart),
        };
    }

    private function applyTimeOrder(QueryBuilder $qb, EventTimeFilter $filter, string $alias = 'e'): void
    {
        match ($filter) {
            EventTimeFilter::Upcoming => $qb->orderBy(sprintf('%s.startDate', $alias), 'ASC'),
            EventTimeFilter::Ongoing => $qb->orderBy(sprintf('%s.startDate', $alias), 'DESC'),
            EventTimeFilter::Past => $qb->orderBy(sprintf('%s.endDate', $alias), 'DESC')
                ->addOrderBy(sprintf('%s.startDate', $alias), 'DESC'),
        };
    }

    /**
     * @param list<int> $memberGroupIds
     */
    public function countVisibleByFilter(array $memberGroupIds, EventTimeFilter $filter, ?string $search = null): int
    {
        $now = ParisClock::now();
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        $this->applyVisibleToUserFilter($qb, $memberGroupIds);
        $this->applyTimeFilter($qb, $filter, $now);
        $this->applySearchFilter($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $memberGroupIds
     *
     * @return list<Event>
     */
    public function findVisibleByFilterPaginated(
        array $memberGroupIds,
        EventTimeFilter $filter,
        int $page,
        int $perPage,
        ?string $search = null,
    ): array {
        $now = ParisClock::now();
        $offset = max(0, ($page - 1) * $perPage);

        $qb = $this->createQueryBuilder('e')
            ->addSelect('g', 'author')
            ->innerJoin('e.relatedGroup', 'g')
            ->leftJoin('e.author', 'author');

        $this->applyVisibleToUserFilter($qb, $memberGroupIds);
        $this->applyTimeFilter($qb, $filter, $now);
        $this->applySearchFilter($qb, $search);
        $this->applyTimeOrder($qb, $filter);

        return $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    private function applySearchFilter(QueryBuilder $qb, ?string $search, string $alias = 'e'): void
    {
        $term = null !== $search ? trim($search) : '';
        if ('' === $term) {
            return;
        }

        $likeTerm = '%'.addcslashes($term, '%_\\').'%';
        $qb->andWhere(sprintf(
            '(LOWER(%1$s.title) LIKE LOWER(:eventSearch) OR LOWER(COALESCE(%1$s.location, \'\')) LIKE LOWER(:eventSearch))',
            $alias,
        ))
            ->setParameter('eventSearch', $likeTerm);
    }

    /**
     * @param list<int> $memberGroupIds
     *
     * @return list<Event>
     */
    public function findUpcomingVisibleToUser(array $memberGroupIds, int $limit): array
    {
        return $this->findVisibleByFilterPaginated($memberGroupIds, EventTimeFilter::Upcoming, 1, $limit);
    }

    /**
     * @return list<Event>
     */
    public function findUpcomingPublic(int $limit): array
    {
        $now = ParisClock::now();

        return $this->createQueryBuilder('e')
            ->addSelect('g', 'author')
            ->innerJoin('e.relatedGroup', 'g')
            ->leftJoin('e.author', 'author')
            ->andWhere('e.visibility = :visibility')
            ->andWhere('e.startDate > :now')
            ->setParameter('visibility', EventVisibility::Public)
            ->setParameter('now', $now)
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Event>
     */
    public function findSharedInStaffCircleByFilter(EventTimeFilter $filter, int $limit = 6): array
    {
        $now = ParisClock::now();
        $qb = $this->createQueryBuilder('e')
            ->addSelect('g', 'author')
            ->innerJoin('e.relatedGroup', 'g')
            ->leftJoin('e.author', 'author')
            ->andWhere('e.sharedInStaffCircle = true')
            ->andWhere('e.visibility = :publicVisibility')
            ->andWhere('g.isStaffCircle = false')
            ->setParameter('publicVisibility', EventVisibility::Public);

        $this->applyTimeFilter($qb, $filter, $now);
        $this->applyTimeOrder($qb, $filter);

        return $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Event>
     */
    public function findByGroupAndFilter(Group $group, EventTimeFilter $filter, int $limit = 6): array
    {
        $now = ParisClock::now();
        $qb = $this->createQueryBuilder('e')
            ->addSelect('author')
            ->leftJoin('e.author', 'author')
            ->andWhere('e.relatedGroup = :group')
            ->setParameter('group', $group);

        $this->applyTimeFilter($qb, $filter, $now);
        $this->applyTimeOrder($qb, $filter);

        return $qb
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Event>
     */
    public function findPastOlderThan(\DateTimeImmutable $threshold): array
    {
        $todayStart = $threshold->setTime(0, 0, 0);

        return $this->createQueryBuilder('e')
            ->andWhere('(e.endDate IS NOT NULL AND e.endDate < :threshold) OR (e.endDate IS NULL AND e.startDate < :todayStart)')
            ->setParameter('threshold', $threshold)
            ->setParameter('todayStart', $todayStart)
            ->getQuery()
            ->getResult();
    }
}
