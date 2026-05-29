<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordChangeService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConfirmPasswordChangeController extends AbstractAppController
{
    #[Route('/profil/mot-de-passe/confirmer/{token}', name: 'app_profile_confirm_password_change', methods: ['GET'])]
    public function confirm(string $token, PasswordChangeService $passwordChange): Response
    {
        if ($passwordChange->confirmPasswordChange($token)) {
            $this->addSuccessFlash($this->trans('profile.password_change_confirmed'));

            return $this->redirectToRoute('app_login');
        }

        $this->addErrorFlash($this->trans('profile.password_change_invalid_token'));

        return $this->redirectToRoute('app_login');
    }
}
