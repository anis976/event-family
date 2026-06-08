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

final class SessionIdleSubscriber implements EventSubscriberInterface
{
    private const SESSION_LAST_ACTIVITY_KEY = '_ef_last_activity';

    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    /** @var list<string> */
    private const EXCLUDED_ROUTE_PREFIXES = [
        'app_login',
        'app_logout',
        'app_register',
        'app_session_activity',
        'app_forgot_password',
        'app_reset_password',
        'app_verify_email',
        'app_resend_verification',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.session.idle_timeout%')]
        private readonly int $idleTimeout,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.path%')]
        private readonly string $adminPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($this->shouldSkip($request)) {
            return;
        }

        if (null === $this->tokenStorage->getToken()?->getUser()) {
            return;
        }

        $session = $request->getSession();
        $now = time();

        if ($this->isAdminRequest($request)) {
            // La zone admin a son propre délai ; on prolonge quand même la session site.
            $session->set(self::SESSION_LAST_ACTIVITY_KEY, $now);

            return;
        }

        $lastActivity = $session->get(self::SESSION_LAST_ACTIVITY_KEY);

        if (\is_int($lastActivity) && ($now - $lastActivity) > $this->idleTimeout) {
            $this->logoutDueToIdle($event);

            return;
        }

        $session->set(self::SESSION_LAST_ACTIVITY_KEY, $now);
    }

    private function shouldSkip(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route', '');

        foreach (self::EXCLUDED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return str_starts_with($request->getPathInfo(), '/build/')
            || str_starts_with($request->getPathInfo(), '/assets/');
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
            $this->urlGenerator->generate('app_login', ['idle' => '1']),
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
