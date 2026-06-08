<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\SiteStaffService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class AdminExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly SiteStaffService $siteStaffService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        $isSiteStaff = $user instanceof User && $this->siteStaffService->isSiteStaff($user);

        return [
            'ef_is_site_staff' => $isSiteStaff,
            'ef_admin_dashboard_url' => $isSiteStaff
                ? $this->urlGenerator->generate('ef_admin')
                : null,
        ];
    }
}
