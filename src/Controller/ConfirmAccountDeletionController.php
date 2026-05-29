<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AccountDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ConfirmAccountDeletionController extends AbstractAppController
{
    #[Route('/profil/suppression/confirmer/{token}', name: 'app_profile_confirm_account_deletion', methods: ['GET'])]
    public function confirm(
        string $token,
        AccountDeletionService $accountDeletion,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $user = $accountDeletion->findUserForValidToken($token);

        if (null === $user) {
            $this->addErrorFlash($this->trans('profile.account_deletion_invalid_token'));

            return $this->redirectToRoute('app_login');
        }

        if ($accountDeletion->ownsGroups($user)) {
            $user->clearAccountDeletion();
            $entityManager->flush();
            $this->addErrorFlash($this->trans('profile.account_deletion_blocked_groups'));

            return $this->redirectToRoute('app_profile');
        }

        try {
            $accountDeletion->confirmAccountDeletion($user);
        } catch (TransportExceptionInterface) {
            $this->logoutIfAuthenticated($security, $user);
            $this->addSuccessFlash($this->trans('profile.account_deletion_done_no_notify'));

            return $this->redirectToRoute('app_login');
        } catch (\LogicException) {
            $this->addErrorFlash($this->trans('profile.account_deletion_blocked_groups'));

            return $this->redirectToRoute('app_profile');
        }

        $this->logoutIfAuthenticated($security, $user);

        $this->addSuccessFlash($this->trans('profile.account_deletion_done'));

        return $this->redirectToRoute('app_login');
    }

    private function logoutIfAuthenticated(Security $security, ?User $deletedUser = null): void
    {
        $currentUser = $security->getUser();
        if (!$currentUser instanceof User) {
            return;
        }

        if (null !== $deletedUser && $currentUser->getId() !== $deletedUser->getId()) {
            return;
        }

        $security->logout(false);
    }
}
