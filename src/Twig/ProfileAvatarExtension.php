<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\UserAvatarService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ProfileAvatarExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserAvatarService $avatarService,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('profile_avatar_visible', $this->isAvatarVisible(...)),
        ];
    }

    public function isAvatarVisible(User $profileUser, ?User $viewer = null): bool
    {
        if (null === $viewer) {
            $current = $this->security->getUser();
            $viewer = $current instanceof User ? $current : null;
        }

        return $this->avatarService->isAvatarVisibleTo($profileUser, $viewer);
    }
}
