<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\GoogleOAuthUserProvisioner;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

final class GoogleAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly GoogleOAuthUserProvisioner $userProvisioner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);
        $googleUser = $client->fetchUserFromToken($accessToken);

        return new SelfValidatingPassport(
            new UserBadge((string) $googleUser->getId(), function () use ($googleUser): User {
                return $this->userProvisioner->provision($googleUser);
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof User && !$user->isOAuthRegistrationComplete()) {
            return new RedirectResponse($this->urlGenerator->generate('app_google_oauth_complete'));
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = $exception->getMessage();
        $flashKey = ('' !== $message && str_starts_with($message, 'auth.google.'))
            ? $message
            : 'auth.google.failed';
        $request->getSession()->getFlashBag()->add(
            'danger',
            $this->translator->trans($flashKey, [], 'messages'),
        );

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

}
