<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserBan;
use App\Enum\PlatformNoticeVariant;
use App\Repository\UserBanRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminPlatformBanService
{
    public function __construct(
        private readonly UserBanRepository $userBanRepository,
        private readonly MessageService $messageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TransactionalEmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        private readonly AdminUserPolicyService $userPolicy,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%ef.moderation_contact%')]
        private readonly string $moderationContact,
    ) {
    }

    public function ban(User $user, User $admin, string $reason): UserBan
    {
        if ($user->isBanned()) {
            throw new \DomainException('admin.crud.user.error_already_banned');
        }

        $trimmedReason = trim($reason);
        if ('' === $trimmedReason) {
            throw new \DomainException('admin.crud.user.error_ban_reason_required');
        }

        $this->assertCanManageTarget($admin, $user);

        if ($user->getId() === $admin->getId()) {
            throw new \DomainException('admin.crud.user.error_self_ban');
        }

        $ban = (new UserBan())
            ->setBannedUser($user)
            ->setAuthor($admin)
            ->setReason($trimmedReason);

        $user->setIsBanned(true);
        $this->entityManager->persist($user);
        $this->entityManager->persist($ban);
        $this->entityManager->flush();

        $this->sendBannedPrivateNotice($user, $trimmedReason);
        $this->sendBannedEmail($user, $trimmedReason);

        return $ban;
    }

    public function unban(User $user, User $actor): void
    {
        if (!$user->isBanned()) {
            return;
        }

        $this->assertCanManageTarget($actor, $user);

        if ($user->getId() === $actor->getId()) {
            throw new \DomainException('admin.crud.user.error_self_unban');
        }

        $user->setIsBanned(false);

        foreach ($this->userBanRepository->findActivePlatformBansForUser($user) as $ban) {
            $ban->setEndsAt(ParisClock::now());
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->sendUnbannedEmail($user);
    }

    private function sendBannedPrivateNotice(User $user, string $reason): void
    {
        $locale = $user->getLocale();
        $content = $this->translator->trans('notice.ban.platform_admin', [
            '%reason%' => $reason,
            '%contact%' => $this->moderationContact,
        ], 'messages', $locale);

        $this->messageService->sendPlatformPrivateNotice($user, $content, PlatformNoticeVariant::System);
    }

    private function sendBannedEmail(User $user, string $reason): void
    {
        try {
            $email = $this->emailHelper->prepare(
                (new TemplatedEmail())
                    ->from(Address::create($this->mailerFrom))
                    ->replyTo(Address::create($this->moderationContact))
                    ->to($user->getEmail())
                    ->subject($this->emailHelper->trans('email.platform_ban.subject', [], $user))
                    ->htmlTemplate('emails/platform_ban.html.twig'),
                $user,
                context: [
                    'user' => $user,
                    'reason' => $reason,
                    'moderation_contact' => $this->moderationContact,
                ],
            );

            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Le bannissement reste effectif même si l'e-mail échoue.
        }
    }

    private function sendUnbannedEmail(User $user): void
    {
        try {
            $email = $this->emailHelper->prepare(
                (new TemplatedEmail())
                    ->from(Address::create($this->mailerFrom))
                    ->to($user->getEmail())
                    ->subject($this->emailHelper->trans('email.platform_unban.subject', [], $user))
                    ->htmlTemplate('emails/platform_unban.html.twig'),
                $user,
                context: ['user' => $user],
            );

            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Le débannissement reste effectif même si l'e-mail échoue.
        }
    }

    private function assertCanManageTarget(User $actor, User $target): void
    {
        if ($this->userPolicy->canBanOrUnban($actor, $target)) {
            return;
        }

        throw new \DomainException($this->userPolicy->getBanDenialKey($actor, $target));
    }
}
