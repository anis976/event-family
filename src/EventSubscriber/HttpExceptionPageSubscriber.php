<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * Affiche les pages d'erreur RapporFam (404, 403 site) au lieu des écrans Symfony bruts
 * (« No route found… », « Access Denied. ») — y compris en environnement de développement.
 */
final class HttpExceptionPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.path%')]
        private readonly string $adminPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (null !== $event->getResponse()) {
            return;
        }

        $statusCode = $this->resolveStatusCode($event->getThrowable());
        if (null === $statusCode) {
            return;
        }

        if (404 === $statusCode) {
            $event->setResponse($this->renderPage(
                'bundles/TwigBundle/Exception/error404.html.twig',
                Response::HTTP_NOT_FOUND,
            ));

            return;
        }

        if (403 === $statusCode && !$this->isAdminRequest($event->getRequest()->getPathInfo())) {
            $event->setResponse($this->renderPage(
                'bundles/TwigBundle/Exception/error403.html.twig',
                Response::HTTP_FORBIDDEN,
            ));
        }
    }

    private function resolveStatusCode(\Throwable $throwable): ?int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        if ($throwable instanceof AccessDeniedException) {
            return Response::HTTP_FORBIDDEN;
        }

        $previous = $throwable->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->resolveStatusCode($previous);
        }

        return null;
    }

    private function renderPage(string $template, int $statusCode): Response
    {
        return new Response(
            $this->twig->render($template),
            $statusCode,
        );
    }

    private function isAdminRequest(string $path): bool
    {
        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }
}
