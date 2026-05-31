<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\AvatarVisibility;
use App\Repository\UserRepository;
use App\Service\UserAvatarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil/avatar', name: 'app_profile_avatar_')]
#[IsGranted('ROLE_USER')]
final class ProfileAvatarController extends AbstractAppController
{
    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(
        Request $request,
        UserAvatarService $avatarService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('profile_avatar_upload', (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Recharge la page et réessaie.');

            return $this->redirectToRoute('app_profile');
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('photo');

        if (null === $uploadedFile) {
            $this->addErrorFlash('Choisis une photo à envoyer.');

            return $this->redirectToRoute('app_profile');
        }

        $visibilityValue = (string) $request->request->get('visibility', AvatarVisibility::Private->value);
        $visibility = AvatarVisibility::tryFrom($visibilityValue) ?? AvatarVisibility::Private;

        $crop = [
            'x' => (int) $request->request->get('cropX'),
            'y' => (int) $request->request->get('cropY'),
            'width' => (int) $request->request->get('cropWidth'),
            'height' => (int) $request->request->get('cropHeight'),
        ];

        try {
            $avatarService->storeUploadedAvatar($user, $uploadedFile, $visibility, $crop);
            $entityManager->flush();
        } catch (\InvalidArgumentException $e) {
            $this->addErrorFlash($e->getMessage());

            return $this->redirectToRoute('app_profile');
        } catch (\Throwable) {
            $this->addErrorFlash('Impossible d\'enregistrer ta photo. Réessaie avec une autre image.');

            return $this->redirectToRoute('app_profile');
        }

        $this->addSuccessFlash('Photo de profil mise à jour.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        UserAvatarService $avatarService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('profile_avatar_delete', (string) $request->request->get('_token'))) {
            $this->addErrorFlash('Session expirée. Recharge la page et réessaie.');

            return $this->redirectToRoute('app_profile');
        }

        $avatarService->deleteAvatar($user);
        $entityManager->flush();
        $entityManager->refresh($user);

        $this->addSuccessFlash('Photo de profil supprimée.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(
        int $id,
        UserRepository $userRepository,
        UserAvatarService $avatarService,
    ): Response {
        $profileUser = $userRepository->findActiveById($id);
        if (null === $profileUser || !$profileUser->hasAvatar()) {
            throw new NotFoundHttpException();
        }

        $viewer = $this->getUser();
        if (!$avatarService->isAvatarVisibleTo($profileUser, $viewer instanceof User ? $viewer : null)) {
            throw new NotFoundHttpException();
        }

        $path = $avatarService->getAvatarAbsolutePath($profileUser);
        if (null === $path) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'avatar-'.$id);
        $response->setPrivate();
        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
