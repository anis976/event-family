<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
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
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function sendPrivateMessage(User $sender, User $recipient, string $content): Message
    {
        $denial = $this->directMessagePolicy->getDenialReason($sender, $recipient);
        if (null !== $denial) {
            throw new \DomainException($denial);
        }

        $message = (new Message())
            ->setAuthor($sender)
            ->setRecipient($recipient)
            ->setContent(trim($content));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function sendGroupMessage(User $author, Group $group, string $content): Message
    {
        if (!$this->groupAccess->isMember($author, $group)) {
            throw new \DomainException('Tu dois être membre du groupe pour publier un message.');
        }

        if ($this->groupAccess->isBannedInGroup($author, $group)) {
            throw new \DomainException('Tu es banni de ce groupe.');
        }

        $message = (new Message())
            ->setAuthor($author)
            ->setRelatedGroup($group)
            ->setContent(trim($content));

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        return $message;
    }

    public function reply(User $author, Message $thread, string $content): Message
    {
        $root = $thread->isRoot() ? $thread : $thread->getParent();
        if (null === $root) {
            throw new \DomainException('Fil de discussion introuvable.');
        }

        if ($root->getReplies()->count() >= Message::MAX_REPLIES) {
            throw new \DomainException('Ce fil a déjà atteint le maximum de '.Message::MAX_REPLIES.' réponses.');
        }

        if ($root->isGroupMessage()) {
            $group = $root->getRelatedGroup();
            if (null === $group || !$this->groupAccess->isMember($author, $group)) {
                throw new \DomainException('Accès refusé.');
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

        return $reply;
    }

    public function markAsRead(Message $message, User $user): void
    {
        if (!$this->canUserViewMessage($user, $message)) {
            throw new \DomainException('Accès refusé.');
        }

        if ($message->getAuthor()->getId() === $user->getId()) {
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
            throw new \DomainException('Tu ne peux pas supprimer ce message.');
        }

        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }

    public function isUnreadFor(Message $message, User $user): bool
    {
        if ($message->getAuthor()->getId() === $user->getId()) {
            return false;
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

    public function canUserViewMessage(User $user, Message $message): bool
    {
        if ($message->isGroupMessage()) {
            $group = $message->getRelatedGroup();

            return null !== $group && $this->groupAccess->isMember($user, $group);
        }

        $root = $message->getParent() ?? $message;

        return $this->isPrivateParticipant($user, $root);
    }

    public function canUserDeleteMessage(User $user, Message $message): bool
    {
        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        if ($message->isPrivateMessage() || null !== $message->getParent() && !$message->isGroupMessage()) {
            $root = $message->getParent() ?? $message;

            return $this->isPrivateParticipant($user, $root);
        }

        if ($message->isGroupMessage()) {
            return $message->getAuthor()->getId() === $user->getId();
        }

        return $message->getAuthor()->getId() === $user->getId()
            || $message->getRecipient()?->getId() === $user->getId();
    }

    private function assertPrivateParticipant(User $user, Message $root): void
    {
        if (!$this->isPrivateParticipant($user, $root)) {
            throw new \DomainException('Accès refusé.');
        }
    }

    private function isPrivateParticipant(User $user, Message $root): bool
    {
        return $root->getAuthor()->getId() === $user->getId()
            || $root->getRecipient()?->getId() === $user->getId();
    }

    private function resolvePrivateCounterpart(User $author, Message $root): User
    {
        if ($root->getAuthor()->getId() === $author->getId()) {
            $recipient = $root->getRecipient();
            if (null === $recipient) {
                throw new \DomainException('Destinataire introuvable.');
            }

            return $recipient;
        }

        return $root->getAuthor();
    }
}
