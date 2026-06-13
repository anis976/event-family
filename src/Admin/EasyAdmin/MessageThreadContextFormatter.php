<?php

declare(strict_types=1);

namespace App\Admin\EasyAdmin;

use App\Entity\Message;
use App\Enum\PlatformNoticeVariant;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MessageThreadContextFormatter
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly AdminDateFormatter $dateFormatter,
    ) {
    }

    public function format(Message $message): string
    {
        $blocks = [];

        $parent = $message->getParent();
        if (null !== $parent) {
            $blocks[] = $this->formatBlock('admin.crud.message.thread.parent', $parent);
        }

        $blocks[] = $this->formatBlock('admin.crud.message.thread.viewed', $message);

        if (!$message->getReplies()->isEmpty()) {
            foreach ($message->getReplies() as $reply) {
                $blocks[] = $this->formatBlock('admin.crud.message.thread.reply', $reply);
            }
        }

        $separator = $this->translator->trans('admin.crud.message.thread.separator', [], 'messages');

        return implode("\n\n".$separator."\n\n", $blocks);
    }

    private function formatBlock(string $labelKey, Message $message): string
    {
        $label = $this->translator->trans($labelKey, [], 'messages');
        $channel = $this->translator->trans($message->getChannelLabelKey(), [], 'messages');
        $author = $this->resolveAuthorLabel($message);
        $date = null !== $message->getCreatedAt()
            ? $this->dateFormatter->formatDateTime($message->getCreatedAt())
            : $this->translator->trans('admin.crud.common.not_available', [], 'messages');
        $hidden = [];
        if (null !== $message->getAuthorHiddenAt()) {
            $hidden[] = $this->translator->trans('admin.crud.message.thread.hidden_by_author', [
                '%date%' => $this->dateFormatter->formatDateTime($message->getAuthorHiddenAt()),
            ], 'messages');
        }
        if (null !== $message->getRecipientHiddenAt()) {
            $hidden[] = $this->translator->trans('admin.crud.message.thread.hidden_by_recipient', [
                '%date%' => $this->dateFormatter->formatDateTime($message->getRecipientHiddenAt()),
            ], 'messages');
        }
        if (null !== $message->getRepliesClosedAt()) {
            $hidden[] = $this->translator->trans('admin.crud.message.thread.thread_closed', [
                '%date%' => $this->dateFormatter->formatDateTime($message->getRepliesClosedAt()),
            ], 'messages');
        }
        $meta = [] !== $hidden
            ? "\n".$this->translator->trans('admin.crud.message.thread.state', [], 'messages').' '.implode(' ; ', $hidden)
            : '';

        return sprintf(
            "[%s] %s (#%s)\n%s: %s\n%s: %s%s\n\n%s",
            $label,
            $channel,
            $message->getId() ?? $this->translator->trans('admin.crud.common.unknown_id', [], 'messages'),
            $this->translator->trans('admin.crud.message.thread.author', [], 'messages'),
            $author,
            $this->translator->trans('admin.crud.message.thread.date', [], 'messages'),
            $date,
            $meta,
            $message->getContent(),
        );
    }

    private function resolveAuthorLabel(Message $message): string
    {
        if ($message->isPlatformNotice()) {
            $variant = $message->getPlatformNoticeVariant() ?? PlatformNoticeVariant::RapproFam;

            return match ($variant) {
                PlatformNoticeVariant::System => $this->translator->trans('admin.crud.message.notice_variant.system', [], 'messages'),
                PlatformNoticeVariant::Moderator => $this->translator->trans('admin.crud.message.notice_variant.moderator', [], 'messages'),
                PlatformNoticeVariant::RapproFam => $this->translator->trans('admin.crud.message.notice_variant.eventfamily', [], 'messages'),
            };
        }

        if ($message->isStaffAnnouncement()) {
            return $this->translator->trans('admin.dashboard.title', [], 'messages');
        }

        return $message->getAuthor()?->getDisplayName()
            ?? $this->translator->trans('admin.crud.message.thread.unknown_author', [], 'messages');
    }
}
