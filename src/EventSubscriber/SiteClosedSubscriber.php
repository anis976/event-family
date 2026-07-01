<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\MaintenanceScheduleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Twig\Environment;

/**
 * Fermeture temporaire du site public (EF_SITE_CLOSED=1 ou fenêtre EF_MAINTENANCE_* active).
 * Les modérateurs / admins connectés conservent l'accès ; /login reste ouvert.
 */
final class SiteClosedSubscriber implements EventSubscriberInterface
{
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    public function __construct(
        private readonly bool $siteClosed,
        private readonly string $adminPath,
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly MaintenanceScheduleService $maintenance,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Après le firewall (8) pour que isGranted('ROLE_MODERATOR') soit fiable.
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $isClosed = $this->maintenance->isSiteEffectivelyClosed($this->siteClosed);

        if (!$isClosed) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isAlwaysAllowed($path)) {
            return;
        }

        try {
            if ($this->authorizationChecker->isGranted('ROLE_MODERATOR')) {
                return;
            }
        } catch (AuthenticationCredentialsNotFoundException) {
            // Pas encore de token sécurité sur cette requête.
        }

        if (null !== $this->tokenStorage->getToken()?->getUser()) {
            $this->logoutCurrentUser($request);
        }

        $maintenanceState = $this->maintenance->getState();

        $html = $this->twig->render('maintenance/site_closed.html.twig', [
            'maintenance' => $maintenanceState,
        ]);

        $response = new Response($html, Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => (string) max(60, $maintenanceState?->secondsUntilEnd ?? 3600),
        ]);

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

    private function logoutCurrentUser(Request $request): void
    {
        $this->tokenStorage->setToken(null);

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }
    }

    private function isAlwaysAllowed(string $path): bool
    {
        if (str_starts_with($path, '/assets')
            || str_starts_with($path, '/bundles')
            || str_starts_with($path, '/_wdt')
            || str_starts_with($path, '/_profiler')
        ) {
            return true;
        }

        if (str_starts_with($path, $this->adminPath)) {
            return true;
        }

        return in_array($path, ['/login', '/mot-de-passe-oublie', '/reinitialiser-mot-de-passe', '/maintenance/statut', '/invitations/api/compteurs'], true)
            || str_starts_with($path, '/connect');
    }
}
