<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountDeletionService
{
    private const int TOKEN_TTL_HOURS = 1;

    private const int MIN_SECONDS_BETWEEN_EMAILS = 300;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserAccountSoftDeleteService $accountSoftDelete,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TransactionalEmailHelper $emailHelper,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function ownsGroups(User $user): bool
    {
        return $this->groupRepository->countOwnedByUser($user) > 0;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function requestAccountDeletion(User $user): void
    {
        $now = ParisClock::now();
        $lastRequest = $user->getAccountDeletionRequestedAt();

        if (
            null !== $lastRequest
            && $lastRequest > $now->modify(sprintf('-%d seconds', self::MIN_SECONDS_BETWEEN_EMAILS))
        ) {
            return;
        }

        $plainToken = bin2hex(random_bytes(32));

        $user->clearPasswordReset();
        $user->clearPendingPasswordChange();
        $user->setAccountDeletionTokenHash($this->hashToken($plainToken));
        $user->setAccountDeletionTokenExpiresAt(
            $now->modify(sprintf('+%d hours', self::TOKEN_TTL_HOURS)),
        );
        $user->setAccountDeletionRequestedAt($now);

        $confirmUrl = $this->urlGenerator->generate(
            'app_profile_confirm_account_deletion',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($user->getEmail())
                ->subject($this->emailHelper->trans('email.account_deletion_confirm.subject', [], $user))
                ->htmlTemplate('emails/account_deletion_confirm.html.twig'),
            $user,
            context: [
                'user' => $user,
                'confirmUrl' => $confirmUrl,
                'expiresHours' => self::TOKEN_TTL_HOURS,
            ],
        );

        $this->mailer->send($email);
    }

    public function findUserForValidToken(string $plainToken): ?User
    {
        if (!$this->isValidTokenFormat($plainToken)) {
            return null;
        }

        $user = $this->userRepository->findOneByAccountDeletionTokenHash($this->hashToken($plainToken));

        if (null === $user || null !== $user->getDeletedAt()) {
            return null;
        }

        $expiresAt = $user->getAccountDeletionTokenExpiresAt();

        if (null === $expiresAt || $expiresAt < ParisClock::now()) {
            $user->clearAccountDeletion();
            $this->entityManager->flush();

            return null;
        }

        return $user;
    }

    /**
     * Soft-delete + anonymisation. Gestion des groupes (successeur) : module Groupes à venir.
     *
     * @throws TransportExceptionInterface
     */
    public function confirmAccountDeletion(User $user): void
    {
        if ($this->ownsGroups($user)) {
            throw new \LogicException('Cannot delete account while owning groups without succession flow.');
        }

        $originalEmail = $user->getEmail();

        $this->accountSoftDelete->softDelete($user);

        $this->sendDeletionDoneNotification($originalEmail, $user->getLocale());
    }

    public function isValidTokenFormat(string $plainToken): bool
    {
        return 64 === strlen($plainToken) && ctype_xdigit($plainToken);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendDeletionDoneNotification(string $originalEmail, string $locale): void
    {
        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($originalEmail)
                ->subject($this->emailHelper->trans('email.account_deletion_done.subject', locale: $locale))
                ->htmlTemplate('emails/account_deletion_done.html.twig'),
            locale: $locale,
            context: [
                'deletedAt' => ParisClock::now(),
            ],
        );

        $this->mailer->send($email);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
