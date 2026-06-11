<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lecture et validation du cookie de consentement (CNIL / RGPD).
 * L'écriture est effectuée côté client (bandeau) ; le serveur valide pour Twig / scripts futurs.
 */
final class CookieConsentService
{
    public const string COOKIE_KEY = 'ef_consent';

    public function __construct(
        #[Autowire('%ef.consent.cookie_name%')]
        private readonly string $cookieName,
        #[Autowire('%ef.consent.version%')]
        private readonly int $version,
        #[Autowire('%ef.consent.ttl_seconds%')]
        private readonly int $ttlSeconds,
    ) {
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    /**
     * @return array{hasChoice: bool, marketing: bool, analytics: bool, timestamp: int|null}
     */
    public function resolveState(?Request $request): array
    {
        $default = [
            'hasChoice' => false,
            'marketing' => false,
            'analytics' => false,
            'timestamp' => null,
        ];

        if (null === $request) {
            return $default;
        }

        $parsed = $this->parseCookieValue($request->cookies->get($this->cookieName));
        if (null === $parsed) {
            return $default;
        }

        return [
            'hasChoice' => true,
            'marketing' => $parsed['marketing'],
            'analytics' => $parsed['analytics'],
            'timestamp' => $parsed['timestamp'],
        ];
    }

    public function hasMarketingConsent(?Request $request): bool
    {
        return $this->resolveState($request)['marketing'];
    }

    public function hasAnalyticsConsent(?Request $request): bool
    {
        return $this->resolveState($request)['analytics'];
    }

    /**
     * @return list<string> Identifiants des catégories optionnelles (hors nécessaires)
     */
    public function getOptionalCategories(): array
    {
        return ['analytics', 'marketing'];
    }

    /**
     * @return list<array{id: string, name_key: string, purpose_key: string, duration_key: string}>
     */
    public function getAnalyticsCookieCatalog(): array
    {
        return [
            [
                'id' => 'ga',
                'name_key' => 'cookie.optional.analytics_ga.name',
                'purpose_key' => 'cookie.optional.analytics_ga.purpose',
                'duration_key' => 'cookie.optional.analytics_ga.duration',
            ],
            [
                'id' => 'gid',
                'name_key' => 'cookie.optional.analytics_gid.name',
                'purpose_key' => 'cookie.optional.analytics_gid.purpose',
                'duration_key' => 'cookie.optional.analytics_gid.duration',
            ],
        ];
    }

    /**
     * @return list<array{id: string, name_key: string, purpose_key: string, duration_key: string}>
     */
    public function getMarketingCookieCatalog(): array
    {
        return [
            [
                'id' => 'gads',
                'name_key' => 'cookie.optional.marketing_gads.name',
                'purpose_key' => 'cookie.optional.marketing_gads.purpose',
                'duration_key' => 'cookie.optional.marketing_gads.duration',
            ],
            [
                'id' => 'ide',
                'name_key' => 'cookie.optional.marketing_ide.name',
                'purpose_key' => 'cookie.optional.marketing_ide.purpose',
                'duration_key' => 'cookie.optional.marketing_ide.duration',
            ],
        ];
    }

    /**
     * @return list<array{id: string, name_key: string, purpose_key: string, duration_key: string}>
     */
    public function getEssentialCookieCatalog(): array
    {
        return [
            [
                'id' => 'session',
                'name_key' => 'cookie.essential.session.name',
                'purpose_key' => 'cookie.essential.session.purpose',
                'duration_key' => 'cookie.essential.session.duration',
            ],
            [
                'id' => 'csrf',
                'name_key' => 'cookie.essential.csrf.name',
                'purpose_key' => 'cookie.essential.csrf.purpose',
                'duration_key' => 'cookie.essential.csrf.duration',
            ],
            [
                'id' => 'locale',
                'name_key' => 'cookie.essential.locale.name',
                'purpose_key' => 'cookie.essential.locale.purpose',
                'duration_key' => 'cookie.essential.locale.duration',
            ],
            [
                'id' => 'remember',
                'name_key' => 'cookie.essential.remember.name',
                'purpose_key' => 'cookie.essential.remember.purpose',
                'duration_key' => 'cookie.essential.remember.duration',
            ],
            [
                'id' => 'consent',
                'name_key' => 'cookie.essential.consent.name',
                'purpose_key' => 'cookie.essential.consent.purpose',
                'duration_key' => 'cookie.essential.consent.duration',
            ],
        ];
    }

    /**
     * @return array{marketing: bool, analytics: bool, timestamp: int}|null
     */
    private function parseCookieValue(mixed $raw): ?array
    {
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $data = $this->decodePayload($raw);
        if (null === $data) {
            $decoded = rawurldecode($raw);
            if ($decoded !== $raw) {
                $data = $this->decodePayload($decoded);
            }
        }

        if (null === $data) {
            return null;
        }

        $version = $data['v'] ?? null;
        if (!\is_int($version) && !(\is_string($version) && ctype_digit((string) $version))) {
            return null;
        }

        if ((int) $version !== $this->version) {
            return null;
        }

        if (true !== ($data['necessary'] ?? null)) {
            return null;
        }

        $marketing = $data['marketing'] ?? null;
        if (!\is_bool($marketing) && !\in_array($marketing, [0, 1, '0', '1', true, false], true)) {
            return null;
        }

        $analytics = $data['analytics'] ?? null;
        if (!\is_bool($analytics) && !\in_array($analytics, [0, 1, '0', '1', true, false], true)) {
            return null;
        }

        $timestamp = $data['ts'] ?? null;
        if (!\is_int($timestamp) && !(\is_string($timestamp) && ctype_digit((string) $timestamp))) {
            return null;
        }

        return [
            'marketing' => filter_var($marketing, FILTER_VALIDATE_BOOLEAN),
            'analytics' => filter_var($analytics, FILTER_VALIDATE_BOOLEAN),
            'timestamp' => (int) $timestamp,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $raw): ?array
    {
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }
}
