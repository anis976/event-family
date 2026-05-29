<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Service\PasswordChangeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil/mot-de-passe', name: 'app_profile_change_password')]
#[IsGranted('ROLE_USER')]
final class ChangePasswordController extends AbstractAppController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        PasswordChangeService $passwordChange,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('newPassword')->getData();

            try {
                $passwordChange->requestPasswordChange($user, $plainPassword);
                $entityManager->flush();
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash($this->trans('profile.password_change_email_failed'));

                return $this->render('profile/change_password.html.twig', [
                    'passwordForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash($this->trans('profile.password_change_email_sent'));

            return $this->redirectToRoute('app_profile');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('profile/change_password.html.twig', [
            'passwordForm' => $form,
        ], $response);
    }
}
