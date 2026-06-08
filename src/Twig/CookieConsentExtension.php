<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\CookieConsentService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class CookieConsentExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly CookieConsentService $cookieConsentService,
        private readonly RequestStack $requestStack,
        #[Autowire('%ef.analytics.measurement_id%')]
        private readonly string $googleAnalyticsMeasurementId,
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        return [
            'ef_consent_cookie_name' => $this->cookieConsentService->getCookieName(),
            'ef_consent_version' => $this->cookieConsentService->getVersion(),
            'ef_consent_ttl' => $this->cookieConsentService->getTtlSeconds(),
            'ef_consent' => $this->cookieConsentService->resolveState($request),
            'ef_consent_essential_catalog' => $this->cookieConsentService->getEssentialCookieCatalog(),
            'ef_consent_analytics_catalog' => $this->cookieConsentService->getAnalyticsCookieCatalog(),
            'ef_google_analytics_id' => trim($this->googleAnalyticsMeasurementId),
        ];
    }
}
