<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableParisTrait;
use App\Enum\GroupMemberRole;
use App\Repository\GroupMemberRepository;
use App\Util\ParisClock;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: GroupMemberRepository::class)]
#[ORM\Table(name: 'ef_group_members')]
#[ORM\UniqueConstraint(name: 'uniq_ef_group_members_user_group', columns: ['user_id', 'group_id'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['user', 'group'], message: 'Cet utilisateur est déjà membre de ce groupe.')]
class GroupMember
{
    use TimestampableParisTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'groupMemberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'groupMembers')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Group $group;

    #[ORM\Column(enumType: GroupMemberRole::class)]
    private GroupMemberRole $role = GroupMemberRole::Member;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\PrePersist]
    public function onPrePersistMember(): void
    {
        $now = ParisClock::now();
        $this->joinedAt = $now;
        $this->lastActivityAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchLastActivityOnUpdate(): void
    {
        $this->lastActivityAt = ParisClock::now();
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

    public function getGroup(): Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): static
    {
        $this->group = $group;

        return $this;
    }

    public function getRole(): GroupMemberRole
    {
        return $this->role;
    }

    public function setRole(GroupMemberRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(\DateTimeImmutable $lastActivityAt): static
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }
}
