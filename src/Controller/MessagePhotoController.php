<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\MessagePhoto;
use App\Entity\User;
use App\Repository\MessagePhotoRepository;
use App\Service\MessagePhotoService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/messages/photo', name: 'app_messages_photo_')]
#[IsGranted('ROLE_USER')]
final class MessagePhotoController extends AbstractAppController
{
    public function __construct(
        private readonly MessagePhotoRepository $messagePhotoRepository,
        private readonly MessagePhotoService $messagePhotoService,
    ) {
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $photo = $this->messagePhotoRepository->find($id);
        if (!$photo instanceof MessagePhoto) {
            throw new NotFoundHttpException();
        }

        $viewer = $this->getUser();
        if (!$viewer instanceof User || !$this->messagePhotoService->isPhotoVisibleTo($photo, $viewer)) {
            throw new NotFoundHttpException();
        }

        $path = $this->messagePhotoService->getPhotoAbsolutePath($photo);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'message-photo-'.$id);
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
