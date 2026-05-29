<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByVerificationTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['verificationTokenHash' => $hash]);
    }

    public function findOneByPasswordChangeTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['passwordChangeTokenHash' => $hash]);
    }

    public function findOneByPasswordResetTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['passwordResetTokenHash' => $hash]);
    }

    /**
     * Compte éligible au reset : actif, vérifié, non banni.
     */
    public function findOneByAccountDeletionTokenHash(string $hash): ?User
    {
        return $this->findOneBy(['accountDeletionTokenHash' => $hash]);
    }

    public function findEligibleForPasswordReset(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = :email')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.isBanned = false')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveById(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id = :id')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByFullNameForAnotherUser(string $firstName, string $lastName, int $excludeUserId): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.firstName = :firstName')
            ->andWhere('u.lastName = :lastName')
            ->andWhere('u.id != :excludeUserId')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('firstName', $firstName)
            ->setParameter('lastName', $lastName)
            ->setParameter('excludeUserId', $excludeUserId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByPseudoForAnotherUser(string $pseudo, int $excludeUserId): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.pseudo = :pseudo')
            ->andWhere('u.id != :excludeUserId')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('pseudo', $pseudo)
            ->setParameter('excludeUserId', $excludeUserId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<User>
     */
    public function searchForGroupInvite(array $excludeUserIds, string $query, int $limit = 25): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.isBanned = false')
            ->andWhere(
                'LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.pseudo) LIKE :q OR LOWER(u.email) LIKE :q'
            )
            ->setParameter('q', '%'.mb_strtolower($query).'%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults($limit);

        if ([] !== $excludeUserIds) {
            $qb->andWhere('u.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeUserIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<User>
     */
    public function findEligibleForGroupInvite(array $excludeUserIds, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.isBanned = false')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.pseudo', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if ([] !== $excludeUserIds) {
            $qb->andWhere('u.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeUserIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function countEligibleForGroupInvite(array $excludeUserIds): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.isVerified = true')
            ->andWhere('u.isBanned = false');

        if ([] !== $excludeUserIds) {
            $qb->andWhere('u.id NOT IN (:excludeIds)')
                ->setParameter('excludeIds', $excludeUserIds);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
