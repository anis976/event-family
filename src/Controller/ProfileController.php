<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileFormType;
use App\Repository\UserRepository;
use App\Service\DirectMessagePolicy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil', name: 'app_profile')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractAppController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addErrorFlash($this->trans('profile.duplicate_fields'));

                return $this->render('profile/edit.html.twig', [
                    'profileForm' => $form,
                    'user' => $user,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash($this->trans('profile.updated'));

            return $this->redirectToRoute('app_profile');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('profile/edit.html.twig', [
            'profileForm' => $form,
            'user' => $user,
        ], $response);
    }

    #[Route('/utilisateur/{id<\d+>}', name: '_show', methods: ['GET'])]
    public function show(
        int $id,
        UserRepository $userRepository,
        DirectMessagePolicy $directMessagePolicy,
    ): Response {
        $profileUser = $userRepository->findActiveById($id);
        if (null === $profileUser) {
            throw $this->createNotFoundException();
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $profileUser->getId()) {
            return $this->redirectToRoute('app_profile');
        }

        $canSendMessage = false;
        $messageDenialReason = null;

        if ($currentUser instanceof User) {
            $canSendMessage = $directMessagePolicy->canSendDirectMessage($currentUser, $profileUser);
            if (!$canSendMessage) {
                $messageDenialReason = $directMessagePolicy->getDenialReason($currentUser, $profileUser);
            }
        }

        return $this->render('profile/show.html.twig', [
            'profileUser' => $profileUser,
            'canSendMessage' => $canSendMessage,
            'messageDenialReason' => $messageDenialReason,
        ]);
    }
}
