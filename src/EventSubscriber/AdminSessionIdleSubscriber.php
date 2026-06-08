<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Déconnexion rapide après inactivité sur la zone d'administration uniquement.
 */
final class AdminSessionIdleSubscriber implements EventSubscriberInterface
{
    private const SESSION_ADMIN_ACTIVITY_KEY = '_ef_admin_last_activity';

    /** Même clé que {@see SessionIdleSubscriber} — activité sur le site public. */
    private const SESSION_SITE_ACTIVITY_KEY = '_ef_last_activity';

    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.path%')]
        private readonly string $adminPath,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.idle_timeout%')]
        private readonly int $idleTimeout,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.session.idle_timeout%')]
        private readonly int $siteIdleTimeout,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!$this->isAdminRequest($request)) {
            return;
        }

        if (null === $this->tokenStorage->getToken()?->getUser()) {
            return;
        }

        $session = $request->getSession();

        if ('ef_admin_session_activity' === $request->attributes->get('_route')) {
            return;
        }

        $now = time();
        $lastAdminActivity = $session->get(self::SESSION_ADMIN_ACTIVITY_KEY);
        $lastSiteActivity = $session->get(self::SESSION_SITE_ACTIVITY_KEY);

        $adminIdle = \is_int($lastAdminActivity) && ($now - $lastAdminActivity) > $this->idleTimeout;
        $siteRecentlyActive = \is_int($lastSiteActivity) && ($now - $lastSiteActivity) <= $this->siteIdleTimeout;

        // Navigation récente sur le site public : ne pas expulser à l’entrée dans l’admin.
        if ($adminIdle && !$siteRecentlyActive) {
            $this->logoutDueToIdle($event);

            return;
        }

        $session->set(self::SESSION_ADMIN_ACTIVITY_KEY, $now);
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }

    private function logoutDueToIdle(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();

        $this->tokenStorage->setToken(null);

        if ($session->isStarted()) {
            $session->invalidate();
        }

        $response = new RedirectResponse(
            $this->urlGenerator->generate('app_login', ['idle' => 'admin']),
        );

        $response->headers->clearCookie(
            self::REMEMBER_ME_COOKIE,
            '/',
            null,
            $request->isSecure(),
            true,
            Cookie::SAMESITE_LAX,
        );

        $event->setResponse($response);
    }
}
