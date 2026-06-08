<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En dev, redirige vers l'hôte de DEFAULT_URI (ex. localhost) si le navigateur
 * utilise un autre nom (127.0.0.1, eventfamily.test, etc.) pour garder session
 * et redirect_uri OAuth alignés.
 */
final class DevCanonicalHostSubscriber implements EventSubscriberInterface
{

    public function __construct(
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 48],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ('dev' !== $this->environment || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/_wdt') || str_starts_with($path, '/_profiler')) {
            return;
        }

        $canonicalHost = parse_url($this->defaultUri, PHP_URL_HOST);
        if (!\is_string($canonicalHost) || '' === $canonicalHost) {
            return;
        }

        $currentHost = $request->getHost();
        if ($currentHost === $canonicalHost) {
            return;
        }

        $scheme = parse_url($this->defaultUri, PHP_URL_SCHEME) ?: $request->getScheme();
        $port = parse_url($this->defaultUri, PHP_URL_PORT);
        if (null === $port) {
            $requestPort = $request->getPort();
            $port = \in_array($requestPort, [80, 443], true) ? null : $requestPort;
        }

        $authority = $canonicalHost;
        if (null !== $port) {
            $authority .= ':'.$port;
        }

        $event->setResponse(new RedirectResponse(
            $scheme.'://'.$authority.$request->getRequestUri(),
            RedirectResponse::HTTP_FOUND,
        ));
    }
}
