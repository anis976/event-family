<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MaintenanceScheduleService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Polling léger pour détecter le début de maintenance sans rechargement manuel.
 * Accessible aux visiteurs (vitrine, login) et aux membres connectés.
 */
#[Route('/maintenance', name: 'app_maintenance')]
final class MaintenanceStatusController extends AbstractAppController
{
    public function __construct(
        private readonly MaintenanceScheduleService $maintenance,
        private readonly bool $siteClosed,
    ) {
    }

    #[Route('/statut', name: '_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        if (!$this->requestWantsJson($request)) {
            return $this->json(['error' => 'json_only'], Response::HTTP_NOT_ACCEPTABLE);
        }

        $isStaff = null !== $this->getUser() && $this->isGranted('ROLE_MODERATOR');

        $response = $this->json([
            'active' => !$isStaff && $this->maintenance->isSiteEffectivelyClosed($this->siteClosed),
            'staff' => $isStaff,
        ]);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    private function requestWantsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }
}
