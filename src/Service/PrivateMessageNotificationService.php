<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PrivateMessageNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TransactionalEmailHelper $emailHelper,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%ef.messages.email_notify_throttle_minutes%')]
        private readonly int $throttleMinutes,
    ) {
    }

    public function notifyRecipient(User $recipient, User $sender, Message $message, Message $root): void
    {
        if ($message->isPlatformNotice() || !$message->isPrivateMessage()) {
            return;
        }

        if (!$recipient->isNotifyPrivateMessageEmail()) {
            return;
        }

        $recipientId = $recipient->getId();
        $rootId = $root->getId();
        if (null === $recipientId || null === $rootId) {
            return;
        }

        if ($this->throttleMinutes <= 0) {
            $this->sendEmail($recipient, $sender, $message);

            return;
        }

        $cacheKey = sprintf('ef_msg_notify_%d_%d', $recipientId, $rootId);
        $shouldSend = false;

        try {
            $this->cache->get($cacheKey, function (ItemInterface $item) use (&$shouldSend): bool {
                $item->expiresAfter($this->throttleMinutes * 60);
                $shouldSend = true;

                return true;
            });

            if ($shouldSend) {
                $this->sendEmail($recipient, $sender, $message);
            }
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Private message notification e-mail failed.', [
                'recipient_id' => $recipientId,
                'exception' => $e->getMessage(),
            ]);
            $this->cache->delete($cacheKey);
        }
    }

    private function sendEmail(User $recipient, User $sender, Message $message): void
    {
        $messagesUrl = $this->urlGenerator->generate(
            'app_messages_private',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $preview = $message->getContent();
        if (\strlen($preview) > 200) {
            $preview = substr($preview, 0, 197).'…';
        }

        $profileUrl = $this->urlGenerator->generate(
            'app_profile',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        ).'#notifications';

        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($recipient->getEmail())
                ->subject($this->emailHelper->trans('email.private_message.subject', [
                    '%name%' => $sender->getDisplayName(),
                ], $recipient))
                ->htmlTemplate('emails/private_message.html.twig')
                ->textTemplate('emails/private_message.txt.twig'),
            $recipient,
            context: [
                'user' => $recipient,
                'sender' => $sender,
                'preview' => $preview,
                'messagesUrl' => $messagesUrl,
            ],
        );

        $this->emailHelper->applyMemberNotificationHeaders($email, $profileUrl);

        $this->mailer->send($email);
    }
}
