<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\PlatformNoticeVariant;
use App\Util\ParisClock;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class InactiveAccountNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendWarning(User $user, int $step, bool $isVerifiedAccount): void
    {
        $this->sendWarningEmail($user, $step, $isVerifiedAccount);

        if ($isVerifiedAccount) {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->buildPrivateNoticeContent($step, $isVerifiedAccount),
                PlatformNoticeVariant::EventFamily,
            );
        }
    }

    public function sendDeletionNotice(string $originalEmail, User $user, bool $wasVerified): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($originalEmail)
            ->subject('EventFamily — Ton compte a été supprimé pour inactivité')
            ->htmlTemplate('emails/inactive_account_deleted.html.twig')
            ->context([
                'deletedAt' => ParisClock::now(),
                'wasVerified' => $wasVerified,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Suppression inactivité : e-mail non envoyé.', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWarningEmail(User $user, int $step, bool $isVerifiedAccount): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject($this->buildEmailSubject($step, $isVerifiedAccount))
            ->htmlTemplate('emails/inactive_account_warning.html.twig')
            ->context([
                'user' => $user,
                'step' => $step,
                'isVerifiedAccount' => $isVerifiedAccount,
                'isFinalWarning' => $isVerifiedAccount ? 2 === $step : true,
            ]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Avertissement inactivité : e-mail non envoyé.', [
                'userId' => $user->getId(),
                'step' => $step,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailSubject(int $step, bool $isVerifiedAccount): string
    {
        if (!$isVerifiedAccount) {
            return 'EventFamily — Active ton compte avant suppression';
        }

        return match ($step) {
            1 => 'EventFamily — Inactivité : premier rappel (1/2)',
            default => 'EventFamily — Inactivité : dernier rappel (2/2)',
        };
    }

    private function buildPrivateNoticeContent(int $step, bool $isVerifiedAccount): string
    {
        if (!$isVerifiedAccount) {
            return <<<'TEXT'
Ton compte EventFamily n'a pas encore été activé.

Connecte-toi et confirme ton adresse e-mail avant la suppression automatique de ton compte pour inactivité.
TEXT;
        }

        if (1 === $step) {
            return <<<'TEXT'
Ton compte EventFamily est inactif depuis longtemps. Il s'agit de ton premier rappel (1/2).

Connecte-toi pour conserver ton compte. Sans connexion, tu recevras un dernier rappel puis ton compte sera supprimé automatiquement.
TEXT;
        }

        return <<<'TEXT'
Ton compte EventFamily est toujours inactif. Il s'agit de ton dernier rappel (2/2).

Connecte-toi rapidement. Sans action de ta part, ton compte sera supprimé automatiquement et tu seras retiré de tes groupes.
TEXT;
    }
}
