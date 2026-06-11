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
        #[Autowire('%ef.adsense.client_id%')]
        private readonly string $googleAdsenseClientId,
        #[Autowire('%ef.adsense.slot_home%')]
        private readonly string $googleAdsenseSlotHome,
        #[Autowire('%ef.adsense.slot_events%')]
        private readonly string $googleAdsenseSlotEvents,
        #[Autowire('%ef.adsense.slot_groups%')]
        private readonly string $googleAdsenseSlotGroups,
        #[Autowire('%ef.adsense.slot_about%')]
        private readonly string $googleAdsenseSlotAbout,
        #[Autowire('%ef.adsense.slot_messages%')]
        private readonly string $googleAdsenseSlotMessages,
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
            'ef_consent_marketing_catalog' => $this->cookieConsentService->getMarketingCookieCatalog(),
            'ef_google_analytics_id' => trim($this->googleAnalyticsMeasurementId),
            'ef_google_adsense_client_id' => trim($this->googleAdsenseClientId),
            'ef_google_adsense_slot_home' => trim($this->googleAdsenseSlotHome),
            'ef_google_adsense_slot_events' => trim($this->googleAdsenseSlotEvents),
            'ef_google_adsense_slot_groups' => trim($this->googleAdsenseSlotGroups),
            'ef_google_adsense_slot_about' => trim($this->googleAdsenseSlotAbout),
            'ef_google_adsense_slot_messages' => trim($this->googleAdsenseSlotMessages),
        ];
    }
}
