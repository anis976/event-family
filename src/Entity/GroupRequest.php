<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Enum\GroupRequestStatus;
use App\Repository\GroupRequestRepository;
use App\Util\ParisClock;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupRequestRepository::class)]
#[ORM\Table(name: 'ef_group_requests')]
#[ORM\Index(name: 'idx_ef_group_requests_user_group', columns: ['user_id', 'related_group_id'])]
#[ORM\Index(name: 'idx_ef_group_requests_group_status', columns: ['related_group_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class GroupRequest implements EfAdminLabelInterface
{
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'groupRequests')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'groupRequests')]
    #[ORM\JoinColumn(name: 'related_group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private Group $relatedGroup;

    #[ORM\Column(enumType: GroupRequestStatus::class, length: 20)]
    private GroupRequestStatus $status = GroupRequestStatus::Pending;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Lu par le chef / modérateur (page gestion des demandes). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= ParisClock::now();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRelatedGroup(): Group
    {
        return $this->relatedGroup;
    }

    public function setRelatedGroup(Group $relatedGroup): static
    {
        $this->relatedGroup = $relatedGroup;

        return $this;
    }

    public function getStatus(): GroupRequestStatus
    {
        return $this->status;
    }

    public function setStatus(GroupRequestStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return GroupRequestStatus::Pending === $this->status;
    }

    public function isRefused(): bool
    {
        return GroupRequestStatus::Refused === $this->status;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function markAsRead(): static
    {
        $this->readAt = ParisClock::now();

        return $this;
    }

    public function getAdminLabel(): string
    {
        return sprintf(
            'Demande #%s — %s → %s (%s)',
            $this->id ?? '?',
            $this->user->getAdminLabel(),
            $this->relatedGroup->getAdminLabel(),
            $this->status->value,
        );
    }
}
