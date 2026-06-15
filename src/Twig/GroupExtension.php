<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Group;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class GroupExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ef_group_name', $this->getGroupName(...)),
            new TwigFunction('ef_group_family_name', $this->getGroupFamilyName(...)),
            new TwigFunction('ef_group_description', $this->getGroupDescription(...)),
        ];
    }

    public function getGroupName(Group $group): string
    {
        if ($group->isStaffCircle()) {
            return $this->translator->trans('ui.groups.staff_circle.group_name');
        }

        return $group->getName();
    }

    public function getGroupFamilyName(Group $group): string
    {
        if ($group->isStaffCircle()) {
            return $this->translator->trans('ui.groups.staff_circle.group_family');
        }

        return $group->getFamilyName();
    }

    public function getGroupDescription(Group $group): ?string
    {
        if ($group->isStaffCircle()) {
            return $this->translator->trans('staff_circle.default_description');
        }

        return $group->getDescription();
    }
}
