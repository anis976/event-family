<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function countPrivateRootThreadsForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere(
                '(m.author = :user AND m.authorHiddenAt IS NULL) OR (m.recipient = :user AND m.recipientHiddenAt IS NULL)',
            )
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Message>
     */
    public function findPrivateRootThreadsForUser(
        User $user,
        int $limit,
        array $repliesVisibleByRootId = [],
        int $defaultRepliesVisible = 30,
    ): array {
        $limit = max(1, $limit);

        $roots = $this->createQueryBuilder('m')
            ->addSelect('author', 'recipient')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.recipient', 'recipient')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere(
                '(m.author = :user AND m.authorHiddenAt IS NULL) OR (m.recipient = :user AND m.recipientHiddenAt IS NULL)',
            )
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->attachRepliesToRoots($roots, $repliesVisibleByRootId, $defaultRepliesVisible);
    }

    public function findActivePrivateThreadBetweenUsers(User $userA, User $userB): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere('m.isPlatformNotice = false')
            ->andWhere('m.repliesClosedAt IS NULL')
            ->andWhere(
                '((m.author = :a AND m.recipient = :b) OR (m.author = :b AND m.recipient = :a))
                AND m.authorHiddenAt IS NULL AND m.recipientHiddenAt IS NULL',
            )
            ->setParameter('a', $userA)
            ->setParameter('b', $userB)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $rootIds
     *
     * @return array<int, int>
     */
    public function countRepliesByRootIds(array $rootIds): array
    {
        $rootIds = array_values(array_unique(array_filter($rootIds, static fn (int $id): bool => $id > 0)));
        if ([] === $rootIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.parent) AS rootId', 'COUNT(m.id) AS replyCount')
            ->andWhere('m.parent IN (:roots)')
            ->setParameter('roots', $rootIds)
            ->groupBy('m.parent')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['rootId']] = (int) $row['replyCount'];
        }

        return $map;
    }

    public function countGroupRootThreads(\App\Entity\Group $group): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Fils racine du groupe, du plus récent au plus ancien (limité).
     *
     * @return list<Message>
     */
    public function findGroupRootThreads(\App\Entity\Group $group, int $limit): array
    {
        $limit = max(1, $limit);

        return $this->createQueryBuilder('m')
            ->distinct()
            ->addSelect('author', 'replies', 'replyAuthor', 'photos')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.replies', 'replies')
            ->leftJoin('replies.author', 'replyAuthor')
            ->leftJoin('m.photos', 'photos')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup = :group')
            ->setParameter('group', $group)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('replies.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneWithRelations(int $id): ?Message
    {
        return $this->createQueryBuilder('m')
            ->addSelect('author', 'recipient', 'relatedGroup', 'parent', 'replies', 'replyAuthor', 'photos')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.recipient', 'recipient')
            ->leftJoin('m.relatedGroup', 'relatedGroup')
            ->leftJoin('m.parent', 'parent')
            ->leftJoin('m.replies', 'replies')
            ->leftJoin('replies.author', 'replyAuthor')
            ->leftJoin('m.photos', 'photos')
            ->andWhere('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUnreadPrivateForUser(User $user): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->leftJoin('m.parent', 'root')
            ->leftJoin(
                MessageRead::class,
                'mr',
                'WITH',
                'mr.message = m AND mr.user = :user',
            )
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere('m.recipient = :user')
            ->andWhere('mr.id IS NULL')
            ->andWhere(
                '(m.parent IS NULL AND m.recipientHiddenAt IS NULL)
                OR (m.parent IS NOT NULL AND (
                    (root.author = :user AND root.authorHiddenAt IS NULL)
                    OR (root.recipient = :user AND root.recipientHiddenAt IS NULL)
                ))',
            )
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $groupIds
     */
    public function countUnreadGroupForUser(User $user, array $groupIds): int
    {
        if ([] === $groupIds) {
            return 0;
        }

        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT m.id)')
            ->from(Message::class, 'm')
            ->leftJoin(
                MessageRead::class,
                'mr',
                'WITH',
                'mr.message = m AND mr.user = :user',
            )
            ->andWhere('m.relatedGroup IN (:groups)')
            ->andWhere('m.author != :user')
            ->andWhere('mr.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('groups', $groupIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $messageIds
     *
     * @return list<int>
     */
    public function findUnreadIdsAmongMessageIds(User $user, array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter($messageIds, static fn (int $id): bool => $id > 0)));

        if ([] === $messageIds) {
            return [];
        }

        $readIds = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(mr.message) AS messageId')
            ->from(MessageRead::class, 'mr')
            ->andWhere('mr.user = :user')
            ->andWhere('mr.message IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $messageIds)
            ->getQuery()
            ->getScalarResult();

        $readIdMap = array_flip(array_map(static fn (array $row): int => (int) $row['messageId'], $readIds));

        $messages = $this->createQueryBuilder('m')
            ->addSelect('parent')
            ->leftJoin('m.parent', 'parent')
            ->andWhere('m.id IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->getQuery()
            ->getResult();

        $unread = [];
        foreach ($messages as $message) {
            if ($message->getAuthor()?->getId() === $user->getId()) {
                continue;
            }

            if ($message->isPrivateMessage() && $message->getRecipient()?->getId() !== $user->getId()) {
                continue;
            }

            $root = $message->getParent() ?? $message;
            if ($root->isPrivateMessage() && $root->isHiddenFor($user)) {
                continue;
            }

            $messageId = $message->getId();
            if (null !== $messageId && !isset($readIdMap[$messageId])) {
                $unread[] = $messageId;
            }
        }

        return $unread;
    }

    /**
     * @param list<Message> $messages
     *
     * @return list<int>
     */
    public function findUnreadIdsForUser(User $user, array $messages): array
    {
        $ids = [];

        foreach ($messages as $message) {
            if (null !== $message->getId()) {
                $ids[] = $message->getId();
            }
        }

        return $this->findUnreadIdsAmongMessageIds($user, $ids);
    }

    /**
     * @param list<Message> $roots
     * @param array<int, int> $repliesVisibleByRootId
     *
     * @return list<Message>
     */
    private function attachRepliesToRoots(
        array $roots,
        array $repliesVisibleByRootId = [],
        int $defaultRepliesVisible = 30,
    ): array {
        if ([] === $roots) {
            return [];
        }

        $rootIds = array_values(array_filter(array_map(
            static fn (Message $message): ?int => $message->getId(),
            $roots,
        )));

        if ([] === $rootIds) {
            return $roots;
        }

        $replies = $this->createQueryBuilder('m')
            ->addSelect('author', 'parent')
            ->innerJoin('m.parent', 'parent')
            ->leftJoin('m.author', 'author')
            ->andWhere('parent IN (:roots)')
            ->setParameter('roots', $rootIds)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $rootMap = [];
        foreach ($roots as $root) {
            $rootId = $root->getId();
            if (null !== $rootId) {
                $rootMap[$rootId] = $root;
            }
        }

        $grouped = [];
        foreach ($replies as $reply) {
            $parentId = $reply->getParent()?->getId();
            if (null === $parentId || !isset($rootMap[$parentId])) {
                continue;
            }

            $grouped[$parentId][] = $reply;
        }

        foreach ($roots as $root) {
            $rootId = $root->getId();
            if (null === $rootId) {
                continue;
            }

            $visible = max(1, $repliesVisibleByRootId[$rootId] ?? $defaultRepliesVisible);
            $allReplies = $grouped[$rootId] ?? [];
            if (\count($allReplies) > $visible) {
                $allReplies = \array_slice($allReplies, -$visible);
            }

            $collection = $root->getReplies();
            foreach ($allReplies as $reply) {
                if (!$collection->contains($reply)) {
                    $collection->add($reply);
                }
            }
        }

        return $roots;
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, int> groupId => unread count
     */
    public function countUnreadGroupMessagesByGroupIds(User $user, array $groupIds): array
    {
        if ([] === $groupIds) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(m.relatedGroup) AS groupId', 'COUNT(m.id) AS unreadCount')
            ->from(Message::class, 'm')
            ->leftJoin(
                MessageRead::class,
                'mr',
                'WITH',
                'mr.message = m AND mr.user = :user',
            )
            ->andWhere('m.relatedGroup IN (:groups)')
            ->andWhere('m.author != :user')
            ->andWhere('mr.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('groups', $groupIds)
            ->groupBy('m.relatedGroup')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['groupId']] = (int) $row['unreadCount'];
        }

        return $map;
    }

    /**
     * @return list<Message>
     */
    public function findUnreadGroupMessagesForUserInGroup(User $user, \App\Entity\Group $group): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin(
                MessageRead::class,
                'mr',
                'WITH',
                'mr.message = m AND mr.user = :user',
            )
            ->andWhere('m.relatedGroup = :group')
            ->andWhere('m.author != :user')
            ->andWhere('mr.id IS NULL')
            ->setParameter('user', $user)
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Message>
     */
    public function findPurgeableRootsOlderThan(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.createdAt < :threshold')
            ->andWhere('m.isPlatformNotice = false')
            ->setParameter('threshold', $threshold)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
