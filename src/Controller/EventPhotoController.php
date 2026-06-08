<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventPhotoSlot;
use App\Repository\EventRepository;
use App\Service\EventAccessService;
use App\Service\EventImageService;
use App\Service\EventPlaceholderService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/evenements')]
#[IsGranted('ROLE_USER')]
final class EventPhotoController extends AbstractAppController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventAccessService $eventAccess,
        private readonly EventImageService $eventImageService,
        private readonly EventPlaceholderService $placeholderService,
    ) {
    }

    #[Route('/photo/{id}/couverture', name: 'app_events_photo_cover', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cover(int $id): Response
    {
        return $this->servePhoto($id, EventPhotoSlot::Cover);
    }

    #[Route('/photo/{id}/detail', name: 'app_events_photo_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id): Response
    {
        return $this->servePhoto($id, EventPhotoSlot::Detail);
    }

    /** @deprecated Alias couverture — compatibilité */
    #[Route('/photo/{id}', name: 'app_events_photo', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function legacy(int $id): Response
    {
        return $this->cover($id);
    }

    private function servePhoto(int $id, EventPhotoSlot $slot): Response
    {
        $user = $this->requireUser();
        $event = $this->eventRepository->findOneWithRelations($id);
        if (null === $event) {
            throw $this->createNotFoundException();
        }

        if (!$this->eventAccess->canView($user, $event)) {
            throw $this->createAccessDeniedException();
        }

        $uploadedResponse = $this->tryServeUploadedPhoto($event, $slot);
        if (null !== $uploadedResponse) {
            return $uploadedResponse;
        }

        return $this->servePlaceholder($event, $slot);
    }

    private function tryServeUploadedPhoto(Event $event, EventPhotoSlot $slot): ?BinaryFileResponse
    {
        $hasPhoto = EventPhotoSlot::Cover === $slot ? $event->hasPhotoCover() : $event->hasPhotoDetail();
        if (!$hasPhoto) {
            return null;
        }

        $path = $this->eventImageService->getPhotoAbsolutePath($event, $slot);
        if (null === $path) {
            return null;
        }

        $filename = EventPhotoSlot::Cover === $slot ? $event->getPhotoCover() : $event->getPhotoDetail();
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename ?? 'photo');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    private function servePlaceholder(Event $event, EventPhotoSlot $slot): Response
    {
        $localPath = $this->placeholderService->getLocalAbsolutePath($event, $slot);
        if (null !== $localPath) {
            $response = new BinaryFileResponse($localPath);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($localPath));
            $response->setPublic();
            $response->setMaxAge(86400);

            return $response;
        }

        return new RedirectResponse($this->placeholderService->getRemoteUrlForEvent($event, $slot));
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
