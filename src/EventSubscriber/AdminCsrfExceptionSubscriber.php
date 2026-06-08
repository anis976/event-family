<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\FlashType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Évite la page brute « Invalid CSRF token. » dans l’admin (session / formulaire périmé).
 */
final class AdminCsrfExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.path%')]
        private readonly string $adminPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 8],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!$this->isCsrfException($event->getThrowable())) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isAdminRequest($request)) {
            return;
        }

        $message = $this->translator->trans('admin.access.csrf_expired', [], 'messages');

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse([
                'error' => $message,
                'reload' => true,
            ], Response::HTTP_FORBIDDEN));

            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (null !== $session) {
            $session->getFlashBag()->add(FlashType::Warning->value, $message);
        }

        $referer = $request->headers->get('Referer');
        $redirectUrl = \is_string($referer) && '' !== $referer && $this->isAdminRequestUrl($referer)
            ? $referer
            : $this->urlGenerator->generate('ef_admin');

        $event->setResponse(new RedirectResponse($redirectUrl));
    }

    private function isCsrfException(\Throwable $throwable): bool
    {
        if ($throwable instanceof InvalidCsrfTokenException) {
            return true;
        }

        // EasyAdmin / Form peuvent parfois remonter une exception générique
        // avec seulement le message "Invalid CSRF token.".
        $normalizedMessage = mb_strtolower($throwable->getMessage());
        if (str_contains($normalizedMessage, 'csrf')) {
            return true;
        }

        $previous = $throwable->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->isCsrfException($previous);
        }

        return false;
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }

    private function isAdminRequestUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return false;
        }

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }
}
