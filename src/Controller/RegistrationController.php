<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractAppController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerification,
    ): Response {
        if ($this->getUser()) {
            $this->addInfoFlash($this->trans('auth.already_logged_in'));

            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setIsVerified(false);

            $entityManager->persist($user);

            try {
                $entityManager->flush();
                $emailVerification->sendVerificationEmail($user);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addErrorFlash($this->trans('auth.register_duplicate'));

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            } catch (TransportExceptionInterface $e) {
                $this->addErrorFlash('flash.registration.email_failed');

                return $this->redirectToRoute('app_login');
            } catch (\Throwable $e) {
                if ($this->getParameter('kernel.debug')) {
                    throw $e;
                }

                $this->addErrorFlash('flash.registration.post_create_error');

                return $this->redirectToRoute('app_login');
            }

            $this->addSuccessFlash($this->trans('auth.register_check_email'));

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
