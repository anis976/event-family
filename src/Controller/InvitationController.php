<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\GroupRequestService;
use App\Service\NotificationCountService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/invitations', name: 'app_invitations')]
#[IsGranted('ROLE_USER')]
final class InvitationController extends AbstractAppController
{
    public function __construct(
        private readonly GroupRequestService $groupRequestService,
        private readonly NotificationCountService $notificationCountService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('', name: '_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->requireUser();

        $receivedInvitations = $this->groupRequestService->findReceivedInvitations($user);
        $staffJoinRequests = $this->groupRequestService->findStaffJoinRequests($user);
        $isStaffAnywhere = $this->notificationCountService->isStaffAnywhere($user);

        $this->groupRequestService->markHubNotificationsAsRead($user);

        return $this->render('invitations/index.html.twig', [
            'receivedInvitations' => $receivedInvitations,
            'staffJoinRequests' => $staffJoinRequests,
            'isStaffAnywhere' => $isStaffAnywhere,
        ]);
    }

    #[Route('/api/compteurs', name: '_counts', methods: ['GET'])]
    public function counts(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->requestWantsJson($request)) {
            return $this->redirectToRoute('app_invitations_index');
        }

        $user = $this->requireUser();

        $response = $this->json($this->notificationCountService->getCountsPayload($user, $this->urlGenerator));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * Réservé au polling JS (fetch) : évite d’afficher du JSON brut en navigation navigateur / Turbo.
     */
    private function requestWantsJson(Request $request): bool
    {
        if ($request->isXmlHttpRequest()) {
            return true;
        }

        $accept = $request->headers->get('Accept', '');

        return str_contains($accept, 'application/json');
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
