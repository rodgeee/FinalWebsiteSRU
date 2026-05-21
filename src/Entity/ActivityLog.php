<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\Repository\EventRepository;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ],
    security: "is_granted('ROLE_ADMIN')"
)]

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Adminuser::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Adminuser $actor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actorName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $actorEmail = null;

    /** External actor reference when the actor is not an Adminuser row (fixture import). */
    #[ORM\Column(name: 'external_actor_ref', length: 36, nullable: true)]
    private ?string $actorId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $actorRole = null;

    #[ORM\Column(length: 50)]
    private string $action;

    #[ORM\Column(length: 100)]
    private string $entityType;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetData = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): ?Adminuser
    {
        return $this->actor;
    }

    public function setActor(?Adminuser $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getActorName(): ?string
    {
        return $this->actorName;
    }

    public function setActorName(?string $actorName): static
    {
        $this->actorName = $actorName;

        return $this;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function setActorEmail(?string $actorEmail): static
    {
        $this->actorEmail = $actorEmail;

        return $this;
    }

    public function getActorId(): ?string
    {
        return $this->actorId;
    }

    public function setActorId(?string $actorId): static
    {
        $this->actorId = $actorId;

        return $this;
    }

    public function getActorRole(): ?string
    {
        return $this->actorRole;
    }

    public function setActorRole(?string $actorRole): static
    {
        $this->actorRole = $actorRole;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getTargetData(): ?string
    {
        return $this->targetData;
    }

    public function setTargetData(?string $targetData): static
    {
        $this->targetData = $targetData;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /**
     * @param array<string, mixed>|null $changes
     */
    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}


