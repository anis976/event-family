<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageRead>
 */
class MessageReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageRead::class);
    }

    public function findOneForMessageAndUser(Message $message, User $user): ?MessageRead
    {
        return $this->findOneBy(['message' => $message, 'user' => $user]);
    }

    /**
     * @param list<int> $messageIds
     *
     * @return array<int, \DateTimeImmutable> messageId => readAt (première lecture par le destinataire)
     */
    public function findReadAtForMessages(array $messageIds): array
    {
        $messageIds = array_values(array_unique(array_filter($messageIds, static fn (int $id): bool => $id > 0)));
        if ([] === $messageIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('mr')
            ->select('IDENTITY(mr.message) AS messageId', 'mr.readAt AS readAt')
            ->andWhere('mr.message IN (:ids)')
            ->setParameter('ids', $messageIds)
            ->orderBy('mr.readAt', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $messageId = (int) $row['messageId'];
            if (isset($map[$messageId])) {
                continue;
            }

            $readAt = $row['readAt'];
            if ($readAt instanceof \DateTimeImmutable) {
                $map[$messageId] = $readAt;
            } elseif (\is_string($readAt)) {
                $map[$messageId] = new \DateTimeImmutable($readAt);
            }
        }

        return $map;
    }
}
