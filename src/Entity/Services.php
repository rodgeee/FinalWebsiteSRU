<?php

namespace App\Entity;

use App\Repository\ServicesRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Staff;
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

#[ORM\Entity(repositoryClass: ServicesRepository::class)]
class Services
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $ShoeName = null;

    #[ORM\Column(length: 255)]
    private ?string $ServiceType = null;

    #[ORM\Column(length: 255)]
    private ?string $Status = null;

    #[ORM\Column(length: 255)]
    private ?string $Note = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $Created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(targetEntity: Staff::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Staff $owner = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 32, options: ['default' => 'admin'])]
    private string $source = 'admin';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShoeName(): ?string
    {
        return $this->ShoeName;
    }

    public function setShoeName(string $ShoeName): static
    {
        $this->ShoeName = $ShoeName;

        return $this;
    }

    public function getServiceType(): ?string
    {
        return $this->ServiceType;
    }

    public function setServiceType(string $ServiceType): static
    {
        $this->ServiceType = $ServiceType;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->Status;
    }

    public function setStatus(string $Status): static
    {
        $this->Status = $Status;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->Note;
    }

    public function setNote(string $Note): static
    {
        $this->Note = $Note;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->Created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $Created_at): static
    {
        $this->Created_at = $Created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getOwner(): ?Staff
    {
        return $this->owner;
    }

    public function setOwner(?Staff $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(?string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }
}
