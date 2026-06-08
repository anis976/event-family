<?php

declare(strict_types=1);

namespace App\Admin\EasyAdmin;

use App\Util\ParisClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Dates admin : fuseau Europe/Paris, format selon la locale active.
 */
final class AdminDateFormatter
{
    public const string PATTERN_DATE = 'dd/MM/yyyy';

    public const string PATTERN_DATE_SHORT = 'dd/MM/yy';

    public const string PATTERN_DATETIME = 'dd/MM/yyyy HH:mm';

    public const string PATTERN_DATETIME_SHORT = 'dd/MM/yy HH:mm';

    private const string PATTERN_DATE_EN = 'MM/dd/yyyy';

    private const string PATTERN_DATE_SHORT_EN = 'MM/dd/yy';

    private const string PATTERN_DATETIME_EN = 'MM/dd/yyyy HH:mm';

    private const string PATTERN_DATETIME_SHORT_EN = 'MM/dd/yy HH:mm';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function toParis(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone(ParisClock::TIMEZONE));
    }

    public function formatDate(\DateTimeInterface $date, bool $shortYear = false): string
    {
        return $this->format($date, $shortYear ? self::PATTERN_DATE_SHORT : self::PATTERN_DATE);
    }

    public function formatDateTime(\DateTimeInterface $date, bool $shortYear = false): string
    {
        return $this->format($date, $shortYear ? self::PATTERN_DATETIME_SHORT : self::PATTERN_DATETIME);
    }

    private function format(\DateTimeInterface $date, string $pattern): string
    {
        $formatter = new \IntlDateFormatter(
            $this->resolveIntlLocale(),
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            ParisClock::TIMEZONE,
            \IntlDateFormatter::GREGORIAN,
            $this->resolvePattern($pattern),
        );

        $formatted = $formatter->format($this->toParis($date));

        return false !== $formatted ? $formatted : '';
    }

    private function resolveIntlLocale(): string
    {
        return 'en' === $this->resolveLocale() ? 'en_GB' : 'fr_FR';
    }

    private function resolveLocale(): string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        return \in_array($locale, ['fr', 'en'], true) ? $locale : 'fr';
    }

    private function resolvePattern(string $pattern): string
    {
        if ('en' !== $this->resolveLocale()) {
            return $pattern;
        }

        return match ($pattern) {
            self::PATTERN_DATE => self::PATTERN_DATE_EN,
            self::PATTERN_DATE_SHORT => self::PATTERN_DATE_SHORT_EN,
            self::PATTERN_DATETIME => self::PATTERN_DATETIME_EN,
            self::PATTERN_DATETIME_SHORT => self::PATTERN_DATETIME_SHORT_EN,
            default => $pattern,
        };
    }
}
