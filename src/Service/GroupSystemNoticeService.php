<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GroupSystemNoticeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getContent(Group $group, ?string $locale = null): string
    {
        $custom = trim($group->getSystemNoticeContent() ?? '');

        if ('' !== $custom) {
            return $custom;
        }

        return $this->translator->trans(
            'group.system_notice.default',
            [],
            'messages',
            $this->resolveLocale($locale),
        );
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

    private function assertAdmin(User $admin): void
    {
        if (!\in_array(User::ROLE_ADMIN, $admin->getRoles(), true)) {
            throw new \DomainException('flash.message.admin_system_only');
        }
    }

    private function resolveLocale(?string $locale): string
    {
        $resolved = $locale
            ?? $this->requestStack->getCurrentRequest()?->getLocale()
            ?? 'fr';

        return \in_array($resolved, ['fr', 'en'], true) ? $resolved : 'fr';
    }
}
