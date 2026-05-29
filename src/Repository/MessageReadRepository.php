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
}
