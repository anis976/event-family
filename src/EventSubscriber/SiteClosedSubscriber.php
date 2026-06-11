<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Twig\Environment;

/**
 * Fermeture temporaire du site public (EF_SITE_CLOSED=1 en prod).
 * Les modérateurs / admins connectés conservent l'accès ; /login reste ouvert.
 */
final class SiteClosedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $siteClosed,
        private readonly string $adminPath,
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->siteClosed || !$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

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

        $html = $this->twig->render('maintenance/site_closed.html.twig');
        $event->setResponse(new Response($html, Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => '3600',
        ]));
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

        return in_array($path, ['/login', '/mot-de-passe-oublie', '/reinitialiser-mot-de-passe'], true)
            || str_starts_with($path, '/connect');
    }
}
