<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\LocaleService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class LocaleExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->getLocale() ?? LocaleService::DEFAULT;

        if (!$this->localeService->isSupported($locale)) {
            $locale = LocaleService::DEFAULT;
        }

        return [
            'ef_locale' => $locale,
            'ef_locale_label' => $this->localeService->getLabel($locale),
            'ef_locale_switch_label' => $this->localeService->getAlternateLabel($locale),
            'ef_locale_switch_url' => $this->urlGenerator->generate('app_locale_switch'),
        ];
    }
}
