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

    /**
     * @return list<Message>
     */
    public function findPrivateRootThreadsForUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('author', 'recipient', 'replies', 'replyAuthor')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.recipient', 'recipient')
            ->leftJoin('m.replies', 'replies')
            ->leftJoin('replies.author', 'replyAuthor')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere('m.author = :user OR m.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Message>
     */
    public function findGroupRootThreads(\App\Entity\Group $group): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('author', 'replies', 'replyAuthor')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.replies', 'replies')
            ->leftJoin('replies.author', 'replyAuthor')
            ->andWhere('m.parent IS NULL')
            ->andWhere('m.relatedGroup = :group')
            ->setParameter('group', $group)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneWithRelations(int $id): ?Message
    {
        return $this->createQueryBuilder('m')
            ->addSelect('author', 'recipient', 'relatedGroup', 'parent', 'replies', 'replyAuthor')
            ->leftJoin('m.author', 'author')
            ->leftJoin('m.recipient', 'recipient')
            ->leftJoin('m.relatedGroup', 'relatedGroup')
            ->leftJoin('m.parent', 'parent')
            ->leftJoin('m.replies', 'replies')
            ->leftJoin('replies.author', 'replyAuthor')
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
            ->leftJoin(
                MessageRead::class,
                'mr',
                'WITH',
                'mr.message = m AND mr.user = :user',
            )
            ->andWhere('m.relatedGroup IS NULL')
            ->andWhere('m.recipient = :user')
            ->andWhere('mr.id IS NULL')
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
     * @param list<Message> $messages
     *
     * @return list<int>
     */
    public function findUnreadIdsForUser(User $user, array $messages): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (Message $message): ?int => $message->getId(),
            $messages,
        )));

        if ([] === $ids) {
            return [];
        }

        $readIds = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(mr.message) AS messageId')
            ->from(MessageRead::class, 'mr')
            ->andWhere('mr.user = :user')
            ->andWhere('mr.message IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getScalarResult();

        $readIdMap = array_flip(array_map(static fn (array $row): int => (int) $row['messageId'], $readIds));

        $unread = [];
        foreach ($messages as $message) {
            if ($message->getAuthor()?->getId() === $user->getId()) {
                continue;
            }

            if ($message->isPrivateMessage() && $message->getRecipient()?->getId() !== $user->getId()) {
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
}
