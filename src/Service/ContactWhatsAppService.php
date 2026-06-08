<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactWhatsAppService
{
    public function __construct(
        #[Autowire('%ef.contact.whatsapp%')]
        private readonly string $whatsappNumber,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->normalizeDigits($this->whatsappNumber);
    }

    public function getDisplayNumber(): string
    {
        $digits = $this->normalizeDigits($this->whatsappNumber);
        if ('' === $digits) {
            return '';
        }

        if (str_starts_with($digits, '33') && 11 === \strlen($digits)) {
            return \sprintf(
                '+33 %s %s %s %s %s',
                $digits[2],
                substr($digits, 3, 2),
                substr($digits, 5, 2),
                substr($digits, 7, 2),
                substr($digits, 9, 2),
            );
        }

        return '+'.$digits;
    }

    public function getChatUrl(): string
    {
        $digits = $this->normalizeDigits($this->whatsappNumber);
        if ('' === $digits) {
            return '';
        }

        $prefill = $this->translator->trans('ui.contact.whatsapp_prefill');

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($prefill);
    }

    private function normalizeDigits(string $number): string
    {
        $digits = preg_replace('/\D+/', '', trim($number)) ?? '';
        if ('' === $digits) {
            return '';
        }

        // Format français local : 06XXXXXXXX → 336XXXXXXXX
        if (str_starts_with($digits, '0') && 10 === \strlen($digits)) {
            $digits = '33'.substr($digits, 1);
        }

        return $digits;
    }
}
