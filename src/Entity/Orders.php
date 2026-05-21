<?php

namespace App\Entity;

use App\Repository\OrdersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
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

#[ORM\Entity(repositoryClass: OrdersRepository::class)]
class Orders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $CustomerName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $Email = null;

    #[ORM\ManyToMany(targetEntity: Products::class)]
    #[ORM\JoinTable(name: 'orders_products')]
    private Collection $Products;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $Quantity = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $TotalPrice = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['COD', 'GCash', 'Bank Transfer', 'Card'])]
    private ?string $PaymentMethod = null;

    #[ORM\Column(name: 'order_status', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'])]
    private ?string $OrderStatus = 'Pending';

    #[ORM\Column]
    private ?\DateTimeImmutable $DateCreated = null;

    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $TrackingNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Remarks = null;

    #[ORM\ManyToOne(targetEntity: Staff::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Staff $owner = null;

    public function __construct()
    {
        $this->Products = new ArrayCollection();
        $this->DateCreated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerName(): ?string
    {
        return $this->CustomerName;
    }

    public function setCustomerName(string $CustomerName): static
    {
        $this->CustomerName = $CustomerName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->Email;
    }

    public function setEmail(string $Email): static
    {
        $this->Email = $Email;
        return $this;
    }

    /**
     * @return Collection<int, Products>
     */
    public function getProducts(): Collection
    {
        return $this->Products;
    }

    public function addProduct(Products $product): static
    {
        if (!$this->Products->contains($product)) {
            $this->Products->add($product);
        }
        return $this;
    }

    public function removeProduct(Products $product): static
    {
        $this->Products->removeElement($product);
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

    public function getTotalPrice(): ?string
    {
        return $this->TotalPrice;
    }

    public function setTotalPrice(string $TotalPrice): static
    {
        $this->TotalPrice = $TotalPrice;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->PaymentMethod;
    }

    public function setPaymentMethod(string $PaymentMethod): static
    {
        $this->PaymentMethod = $PaymentMethod;
        return $this;
    }

    public function getOrderStatus(): ?string
    {
        return $this->OrderStatus;
    }

    public function setOrderStatus(string $OrderStatus): static
    {
        $this->OrderStatus = $OrderStatus;
        return $this;
    }

    public function getDateCreated(): ?\DateTimeImmutable
    {
        return $this->DateCreated;
    }

    public function setDateCreated(\DateTimeImmutable $DateCreated): static
    {
        $this->DateCreated = $DateCreated;
        return $this;
    }

    public function getOrderNumber(): ?string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): static
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /** Customer- and staff-facing reference (falls back to legacy id). */
    public function getDisplayOrderNumber(): string
    {
        if ($this->orderNumber !== null && $this->orderNumber !== '') {
            return $this->orderNumber;
        }

        return $this->id !== null ? sprintf('SRU-LEGACY-%04d', $this->id) : 'SRU-PENDING';
    }

    public function getTrackingNumber(): ?string
    {
        return $this->TrackingNumber;
    }

    public function setTrackingNumber(?string $TrackingNumber): static
    {
        $this->TrackingNumber = $TrackingNumber;
        return $this;
    }

    public function getRemarks(): ?string
    {
        return $this->Remarks;
    }

    public function setRemarks(?string $Remarks): static
    {
        $this->Remarks = $Remarks;
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


