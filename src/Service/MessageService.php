<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use App\Enum\PlatformNoticeVariant;
use App\Repository\GroupMemberRepository;
use App\Repository\MessageReadRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MessageService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly MessageReadRepository $messageReadRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly GroupAccessService $groupAccess,
        private readonly DirectMessagePolicy $directMessagePolicy,
        private readonly SiteStaffService $siteStaff,
        private readonly PrivateMessageRateLimitService $privateMessageRateLimit,
        private readonly GroupMessageRateLimitService $groupMessageRateLimit,
        private readonly PrivateMessageNotificationService $privateMessageNotification,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sendPrivateMessage(User $sender, User $recipient, string $content): Message
    {
        $denial = $this->directMessagePolicy->getDenialReason($sender, $recipient);
        if (null !== $denial) {
            throw new \DomainException($denial);
        }

        $existingThread = $this->messageRepository->findActivePrivateThreadBetweenUsers($sender, $recipient);
        if (null !== $existingThread) {
            return $this->reply($sender, $existingThread, $content);
        }

        $this->privateMessageRateLimit->assertCanSend($sender);

        $message = (new Message())
            ->setAuthor($sender)
            ->setRecipient($recipient)
            ->setContent(trim($content));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        $this->privateMessageNotification->notifyRecipient($recipient, $sender, $message, $message);

        return $message;
    }

    public function sendGroupMessage(User $author, Group $group, string $content): Message
    {
        if (!$this->groupAccess->isMember($author, $group)) {
            throw new \DomainException('flash.message.must_be_member');
        }

        if ($this->groupAccess->isBannedInGroup($author, $group)) {
            throw new \DomainException('flash.message.banned_in_group');
        }

        $this->groupMessageRateLimit->assertCanSend($author);

        $message = (new Message())
            ->setAuthor($author)
            ->setRelatedGroup($group)
            ->setContent(trim($content));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function sendStaffAnnouncement(User $author, Group $group, string $content): Message
    {
        if (!$this->siteStaff->isSiteStaff($author)) {
            throw new \DomainException('flash.message.staff_only_announcement');
        }

        $message = (new Message())
            ->setAuthor($author)
            ->setRelatedGroup($group)
            ->setContent(trim($content))
            ->setIsStaffAnnouncement(true);

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function sendPlatformPrivateNotice(
        User $recipient,
        string $content,
        PlatformNoticeVariant $variant = PlatformNoticeVariant::EventFamily,
        ?User $sentBy = null,
    ): Message {
        $message = (new Message())
            ->setRecipient($recipient)
            ->setContent(trim($content))
            ->setIsPlatformNotice(true)
            ->setPlatformNoticeVariant($variant);

        if (null !== $sentBy) {
            $message->setAuthor($sentBy);
        }

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function reply(User $author, Message $thread, string $content): Message
    {
        $root = $thread->isRoot() ? $thread : $thread->getParent();
        if (null === $root) {
            throw new \DomainException('flash.message.thread_not_found');
        }

        if ($root->isStaffAnnouncement()) {
            throw new \DomainException('flash.message.no_reply_staff');
        }

        if ($root->isPlatformNotice()) {
            throw new \DomainException('flash.message.no_reply_platform');
        }

        if (!$root->isGroupMessage() && $root->areRepliesClosed()) {
            throw new \DomainException('flash.message.thread_closed');
        }

        if (!$root->isGroupMessage()) {
            $this->privateMessageRateLimit->assertCanSend($author);
        } else {
            $this->groupMessageRateLimit->assertCanSend($author);
        }

        if ($root->isGroupMessage()) {
            $group = $root->getRelatedGroup();
            if (null === $group || !$this->groupAccess->isMember($author, $group)) {
                throw new \DomainException('flash.message.access_denied');
            }

            $reply = (new Message())
                ->setAuthor($author)
                ->setRelatedGroup($group)
                ->setParent($root)
                ->setContent(trim($content));
        } else {
            $this->assertPrivateParticipant($author, $root);
            $recipient = $this->resolvePrivateCounterpart($author, $root);

            $reply = (new Message())
                ->setAuthor($author)
                ->setRecipient($recipient)
                ->setParent($root)
                ->setContent(trim($content));
        }

        $this->entityManager->persist($reply);
        $this->entityManager->flush();

        if (!$root->isGroupMessage()) {
            $recipient = $reply->getRecipient();
            if (null !== $recipient) {
                $this->privateMessageNotification->notifyRecipient($recipient, $author, $reply, $root);
            }
        }

        return $reply;
    }

    public function markAsRead(Message $message, User $user): void
    {
        if (!$this->canUserViewMessage($user, $message)) {
            throw new \DomainException('flash.message.access_denied');
        }

        if ($message->getAuthor()?->getId() === $user->getId()) {
            return;
        }

        if (null !== $this->messageReadRepository->findOneForMessageAndUser($message, $user)) {
            return;
        }

        $read = (new MessageRead())
            ->setMessage($message)
            ->setUser($user);

        $this->entityManager->persist($read);
        $this->entityManager->flush();
    }

    public function deleteMessage(User $actor, Message $message): void
    {
        if (!$this->canUserDeleteMessage($actor, $message)) {
            throw new \DomainException('flash.message.cannot_delete');
        }

        if ($message->isPlatformNotice()) {
            throw new \DomainException('flash.message.platform_cannot_delete');
        }

        if (\in_array(User::ROLE_ADMIN, $actor->getRoles(), true)) {
            $root = $message->getParent() ?? $message;
            $this->entityManager->remove($root);

            $this->entityManager->flush();

            return;
        }

        if ($message->isGroupMessage()) {
            $this->entityManager->remove($message);
            $this->entityManager->flush();

            return;
        }

        $root = $message->getParent() ?? $message;
        if (!$root->isPrivateMessage() || !$root->isRoot()) {
            throw new \DomainException('flash.message.hide_root_only');
        }

        $root->hideFor($actor);
        $this->markPrivateThreadAsReadForUser($actor, $root);
        $this->entityManager->flush();
    }

    /**
     * Évite les notifications fantômes après masquage d'un fil MP.
     */
    private function markPrivateThreadAsReadForUser(User $user, Message $root): void
    {
        $messages = [$root, ...$root->getReplies()->toArray()];

        foreach ($messages as $message) {
            if ($message->getAuthor()?->getId() === $user->getId()) {
                continue;
            }

            if (null !== $this->messageReadRepository->findOneForMessageAndUser($message, $user)) {
                continue;
            }

            $this->entityManager->persist(
                (new MessageRead())
                    ->setMessage($message)
                    ->setUser($user),
            );
        }
    }

    public function isUnreadFor(Message $message, User $user): bool
    {
        if ($message->getAuthor()?->getId() === $user->getId()) {
            return false;
        }

        if ($message->isPlatformNotice()) {
            return $message->getRecipient()?->getId() === $user->getId();
        }

        if (!$this->canUserViewMessage($user, $message)) {
            return false;
        }

        if ($message->isPrivateMessage() && $message->getRecipient()?->getId() !== $user->getId()) {
            return false;
        }

        return null === $this->messageReadRepository->findOneForMessageAndUser($message, $user);
    }

    /**
     * @param list<int> $groupIds
     *
     * @return array<int, int>
     */
    public function getUnreadCountByGroupIds(User $user, array $groupIds): array
    {
        return $this->messageRepository->countUnreadGroupMessagesByGroupIds($user, $groupIds);
    }

    public function markGroupMessagesAsViewed(User $user, Group $group): void
    {
        if (!$this->groupAccess->isMember($user, $group)) {
            return;
        }

        foreach ($this->messageRepository->findUnreadGroupMessagesForUserInGroup($user, $group) as $message) {
            if (null !== $this->messageReadRepository->findOneForMessageAndUser($message, $user)) {
                continue;
            }

            $this->entityManager->persist(
                (new MessageRead())
                    ->setMessage($message)
                    ->setUser($user),
            );
        }

        $this->entityManager->flush();
    }

    /**
     * @return array{private: int, group: int}
     */
    public function getUnreadCounts(User $user): array
    {
        $groupIds = $this->groupMemberRepository->findGroupIdsForUser($user);

        return [
            'private' => $this->messageRepository->countUnreadPrivateForUser($user),
            'group' => $this->messageRepository->countUnreadGroupForUser($user, $groupIds),
        ];
    }

    /**
     * @param list<Message> $threads
     *
     * @return list<int>
     */
    public function collectUnreadIds(User $user, array $threads): array
    {
        $messages = [];
        foreach ($threads as $thread) {
            $messages[] = $thread;
            foreach ($thread->getReplies() as $reply) {
                $messages[] = $reply;
            }
        }

        return $this->messageRepository->findUnreadIdsForUser($user, $messages);
    }

    /**
     * Accusés de lecture pour les messages privés envoyés par l'utilisateur connecté.
     *
     * @param list<Message> $threads
     *
     * @return array<int, \DateTimeImmutable> messageId => readAt
     */
    public function collectReadReceiptsForViewer(User $viewer, array $threads): array
    {
        $viewerId = $viewer->getId();
        if (null === $viewerId) {
            return [];
        }

        $messageIds = [];
        foreach ($threads as $thread) {
            if ($thread->isGroupMessage() || $thread->isPlatformNotice()) {
                continue;
            }

            if ($thread->getAuthor()?->getId() === $viewerId && null !== $thread->getId()) {
                $messageIds[] = $thread->getId();
            }

            foreach ($thread->getReplies() as $reply) {
                if ($reply->getAuthor()?->getId() === $viewerId && null !== $reply->getId()) {
                    $messageIds[] = $reply->getId();
                }
            }
        }

        return $this->messageReadRepository->findReadAtForMessages($messageIds);
    }

    public function canUserViewMessage(User $user, Message $message): bool
    {
        if ($message->isGroupMessage()) {
            $group = $message->getRelatedGroup();

            return null !== $group && (
                $this->groupAccess->isMember($user, $group)
                || $this->siteStaff->isSiteStaff($user)
            );
        }

        $root = $message->getParent() ?? $message;

        if ($root->isHiddenFor($user)) {
            return false;
        }

        return $this->isPrivateParticipant($user, $root);
    }

    public function canUserDeleteMessage(User $user, Message $message): bool
    {
        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        if ($message->isPlatformNotice()) {
            return false;
        }

        if ($message->isStaffAnnouncement() && $this->siteStaff->isSiteStaff($user)) {
            return $message->getAuthor()?->getId() === $user->getId();
        }

        if ($message->isPrivateMessage() || null !== $message->getParent() && !$message->isGroupMessage()) {
            $root = $message->getParent() ?? $message;

            return $this->isPrivateParticipant($user, $root);
        }

        if ($message->isGroupMessage()) {
            return $message->getAuthor()?->getId() === $user->getId();
        }

        return $message->getAuthor()?->getId() === $user->getId()
            || $message->getRecipient()?->getId() === $user->getId();
    }

    private function assertPrivateParticipant(User $user, Message $root): void
    {
        if (!$this->isPrivateParticipant($user, $root)) {
            throw new \DomainException('flash.message.access_denied');
        }
    }

    private function isPrivateParticipant(User $user, Message $root): bool
    {
        if ($root->isPlatformNotice()) {
            return $root->getRecipient()?->getId() === $user->getId();
        }

        return $root->getAuthor()?->getId() === $user->getId()
            || $root->getRecipient()?->getId() === $user->getId();
    }

    private function resolvePrivateCounterpart(User $author, Message $root): User
    {
        if ($root->getAuthor()?->getId() === $author->getId()) {
            $recipient = $root->getRecipient();
            if (null === $recipient) {
                throw new \DomainException('flash.message.counterpart_not_found');
            }

            return $recipient;
        }

        $author = $root->getAuthor();
        if (null === $author) {
            throw new \DomainException('flash.message.author_not_found');
        }

        return $author;
    }
}
