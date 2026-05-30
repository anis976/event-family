<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;

final class GroupSystemNoticeService
{
    public const DEFAULT_NOTICE = <<<'TEXT'
Bienvenue dans l'espace messages de ce groupe.

Respecte les autres membres, reste courtois et signale tout comportement inapproprié au chef ou au modérateur du groupe. Les règles de la plateforme EventFamily s'appliquent ici.
TEXT;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getContent(Group $group): string
    {
        $custom = trim($group->getSystemNoticeContent() ?? '');

        return '' !== $custom ? $custom : self::DEFAULT_NOTICE;
    }

    public function isCustomized(Group $group): bool
    {
        return '' !== trim($group->getSystemNoticeContent() ?? '');
    }

    public function updateNotice(Group $group, User $admin, string $content): void
    {
        $this->assertAdmin($admin);

        $group->setSystemNoticeContent(trim($content));
        $group->setSystemNoticeUpdatedAt(ParisClock::now());
        $this->entityManager->flush();
    }

    public function resetToDefault(Group $group, User $admin): void
    {
        $this->assertAdmin($admin);

        $group->setSystemNoticeContent(null);
        $group->setSystemNoticeUpdatedAt(null);
        $this->entityManager->flush();
    }

    private function assertAdmin(User $user): void
    {
        if (!\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            throw new \DomainException('Seul un administrateur du site peut gérer ce message.');
        }
    }
}
