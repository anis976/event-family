<?php

declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class EfDateExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('ef_datetime', $this->formatDateTime(...)),
            new TwigFilter('ef_date', $this->formatDate(...)),
        ];
    }

    public function formatDateTime(?\DateTimeInterface $date, ?string $locale = null): string
    {
        if (null === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            $this->resolveLocale($locale),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT,
            'Europe/Paris',
        );

        $formatted = $formatter->format($date);

        return false !== $formatted ? $formatted : '';
    }

    public function formatDate(?\DateTimeInterface $date, ?string $locale = null): string
    {
        if (null === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            $this->resolveLocale($locale),
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE,
            'Europe/Paris',
        );

        $formatted = $formatter->format($date);

        return false !== $formatted ? $formatted : '';
    }

    private function resolveLocale(?string $locale): string
    {
        $resolved = $locale ?? $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';

        return \in_array($resolved, ['fr', 'en'], true) ? $resolved : 'fr';
    }
}
