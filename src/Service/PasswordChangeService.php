<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordChangeService
{
    private const int TOKEN_TTL_HOURS = 1;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TransactionalEmailHelper $emailHelper,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Enregistre le nouveau mot de passe en attente et envoie l'e-mail de confirmation.
     *
     * @throws TransportExceptionInterface
     */
    public function requestPasswordChange(User $user, string $plainNewPassword): void
    {
        $plainToken = bin2hex(random_bytes(32));

        $user->setPendingPasswordHash($this->passwordHasher->hashPassword($user, $plainNewPassword));
        $user->setPasswordChangeTokenHash($this->hashToken($plainToken));
        $user->setPasswordChangeTokenExpiresAt(
            ParisClock::now()->modify(sprintf('+%d hours', self::TOKEN_TTL_HOURS)),
        );

        $confirmUrl = $this->urlGenerator->generate(
            'app_profile_confirm_password_change',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($user->getEmail())
                ->subject($this->emailHelper->trans('email.password_change.subject', [], $user))
                ->htmlTemplate('emails/password_change_confirm.html.twig'),
            $user,
            context: [
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'expiresHours' => self::TOKEN_TTL_HOURS,
            ],
        );

        $this->mailer->send($email);
    }

    public function confirmPasswordChange(string $plainToken): bool
    {
        if (strlen($plainToken) !== 64 || !ctype_xdigit($plainToken)) {
            return false;
        }

        $user = $this->userRepository->findOneByPasswordChangeTokenHash($this->hashToken($plainToken));

        if (null === $user) {
            return false;
        }

        $expiresAt = $user->getPasswordChangeTokenExpiresAt();
        $pendingHash = $user->getPendingPasswordHash();

        if (
            null === $expiresAt
            || $expiresAt < ParisClock::now()
            || null === $pendingHash
            || '' === $pendingHash
        ) {
            $user->clearPendingPasswordChange();
            $this->entityManager->flush();

            return false;
        }

        $user->setPassword($pendingHash);
        $user->clearPendingPasswordChange();

        $this->entityManager->flush();

        return true;
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
