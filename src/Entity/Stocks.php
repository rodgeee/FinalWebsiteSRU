<?php

namespace App\Entity;

use App\Repository\StocksRepository;
use Doctrine\DBAL\Types\Types;
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

#[ORM\Entity(repositoryClass: StocksRepository::class)]
class Stocks
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Products::class, inversedBy: 'stocks', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Products $Product = null;

    #[ORM\Column]
    private ?int $Quantity = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $Created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $Updated_at = null;

    #[ORM\ManyToOne(targetEntity: Staff::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Staff $owner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Products
    {
        return $this->Product;
    }

    public function setProduct(?Products $Product): static
    {
        $this->Product = $Product;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->Quantity;
    }

    public function setQuantity(int $Quantity): static
    {
        $this->Quantity = $Quantity;

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
        return $this->Updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $Updated_at): static
    {
        $this->Updated_at = $Updated_at;

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
}
