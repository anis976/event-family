<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\DeleteAccountFormType;
use App\Service\AccountDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil/suppression', name: 'app_profile_delete_account')]
#[IsGranted('ROLE_USER')]
final class DeleteAccountController extends AbstractAppController
{
    #[Route('', name: '', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        AccountDeletionService $accountDeletion,
        EntityManagerInterface $entityManager,
        Security $security,
        #[Autowire(service: 'limiter.account_deletion_request')]
        RateLimiterFactory $accountDeletionRequestLimiter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($accountDeletion->ownsGroups($user)) {
            return $this->render('profile/delete_account_blocked.html.twig');
        }

        $form = $this->createForm(DeleteAccountFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $accountDeletionRequestLimiter->create($request->getClientIp() ?? 'unknown');
            $rateLimit = $limiter->consume(1);

            if (!$rateLimit->isAccepted()) {
                $this->addWarningFlash($this->trans('profile.account_deletion_rate_limited'));

                return $this->redirectToRoute('app_profile_delete_account');
            }

            try {
                $accountDeletion->requestAccountDeletion($user);
                $entityManager->flush();
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash($this->trans('profile.account_deletion_email_failed'));

                return $this->render('profile/delete_account.html.twig', [
                    'deleteAccountForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash($this->trans('profile.account_deletion_email_sent'));

            if (null !== $security->getUser()) {
                $security->logout(false);
            }

            return $this->redirectToRoute('app_login');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('profile/delete_account.html.twig', [
            'deleteAccountForm' => $form,
        ], $response);
    }
}
