<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Repository\MessageReadRepository;
use App\Util\ParisClock;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageReadRepository::class)]
#[ORM\Table(name: 'ef_message_reads')]
#[ORM\UniqueConstraint(name: 'uniq_ef_message_reads_message_user', columns: ['message_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class MessageRead implements EfAdminLabelInterface
{
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'reads')]
    #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->readAt ??= ParisClock::now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function getAdminLabel(): string
    {
        return sprintf(
            'Lu #%s — %s sur %s',
            $this->id ?? '?',
            $this->user->getAdminLabel(),
            $this->message->getAdminLabel(),
        );
    }
}
