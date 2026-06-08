<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocaleService
{
    public const SESSION_KEY = '_ef_locale';

    public const COOKIE_KEY = 'ef_locale';

    public const DEFAULT = 'fr';

    /** @var list<string> */
    public const SUPPORTED = ['fr', 'en'];

    private const COOKIE_TTL = 31536000; // 1 an

    public function resolveLocale(?Request $request, ?User $user = null): string
    {
        if (null === $request) {
            return self::DEFAULT;
        }

        $explicit = $request->query->get('_locale');
        if (\is_string($explicit) && $this->isSupported($explicit)) {
            return $explicit;
        }

        $sessionLocale = $request->getSession()->get(self::SESSION_KEY);
        if (\is_string($sessionLocale) && $this->isSupported($sessionLocale)) {
            return $sessionLocale;
        }

        $cookieLocale = $request->cookies->get(self::COOKIE_KEY);
        if (\is_string($cookieLocale) && $this->isSupported($cookieLocale)) {
            return $cookieLocale;
        }

        if (null !== $user && $this->isSupported($user->getLocale())) {
            return $user->getLocale();
        }

        return self::DEFAULT;
    }

    public function persistLocale(Request $request, string $locale, ?User $user = null): string
    {
        if (!$this->isSupported($locale)) {
            $locale = self::DEFAULT;
        }

        $request->getSession()->set(self::SESSION_KEY, $locale);

        if (null !== $user && $user->getLocale() !== $locale) {
            $user->setLocale($locale);
        }

        return $locale;
    }

    public function attachLocaleCookie(Response $response, string $locale): void
    {
        if (!$this->isSupported($locale)) {
            return;
        }

        $response->headers->setCookie(
            Cookie::create(self::COOKIE_KEY)
                ->withValue($locale)
                ->withExpires(time() + self::COOKIE_TTL)
                ->withPath('/')
                ->withSecure(false)
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX),
        );
    }

    public function toggle(string $current): string
    {
        return 'fr' === $current ? 'en' : 'fr';
    }

    public function getLabel(string $locale): string
    {
        return match ($locale) {
            'en' => 'English',
            default => 'Français',
        };
    }

    public function getAlternateLabel(string $current): string
    {
        return $this->getLabel($this->toggle($current));
    }

    public function isSupported(string $locale): bool
    {
        return \in_array($locale, self::SUPPORTED, true);
    }
}
