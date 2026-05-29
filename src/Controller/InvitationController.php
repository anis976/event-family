<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\GroupRequestService;
use App\Service\NotificationCountService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/invitations', name: 'app_invitations')]
#[IsGranted('ROLE_USER')]
final class InvitationController extends AbstractAppController
{
    public function __construct(
        private readonly GroupRequestService $groupRequestService,
        private readonly NotificationCountService $notificationCountService,
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
    public function counts(): JsonResponse
    {
        $user = $this->requireUser();

        return $this->json($this->notificationCountService->getCounts($user));
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
