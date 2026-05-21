<?php

namespace App\Entity;

use App\Repository\ProductsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

#[ORM\Entity(repositoryClass: ProductsRepository::class)]
class Products
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Name = null;

    #[ORM\Column(length: 255)]
    private ?string $Color = null;

    #[ORM\Column(length: 255)]
    private ?string $Size = null;

    #[ORM\OneToMany(targetEntity: Stocks::class, mappedBy: 'Product', cascade: ['persist', 'remove'])]
    private Collection $stocks;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $Price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Image = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $Images = null;

    #[ORM\ManyToOne(targetEntity: Staff::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Staff $owner = null;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->Name;
    }

    public function setName(string $Name): static
    {
        $this->Name = $Name;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->Color;
    }

    public function setColor(string $Color): static
    {
        $this->Color = $Color;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->Size;
    }

    public function setSize(string $Size): static
    {
        $this->Size = $Size;

        return $this;
    }

    /**
     * @return Collection<int, Stocks>
     */
    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function addStock(Stocks $stock): static
    {
        if (!$this->stocks->contains($stock)) {
            $this->stocks->add($stock);
            $stock->setProduct($this);
        }

        return $this;
    }

    public function removeStock(Stocks $stock): static
    {
        if ($this->stocks->removeElement($stock)) {
            if ($stock->getProduct() === $this) {
                $stock->setProduct(null);
            }
        }

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(?string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->Price;
    }

    public function setPrice(string $Price): static
    {
        $this->Price = $Price;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->Image;
    }

    public function setImage(?string $Image): static
    {
        $this->Image = $Image;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getImages(): array
    {
        if (!$this->Images) {
            return [];
        }

        return array_values(array_filter($this->Images, static fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * @param string[]|null $Images
     */
    public function setImages(?array $Images): static
    {
        if ($Images === null) {
            $this->Images = null;

            return $this;
        }

        $clean = [];
        foreach ($Images as $path) {
            if (is_string($path) && $path !== '') {
                $clean[] = $path;
            }
        }

        $this->Images = array_slice($clean, 0, 4);

        return $this;
    }

    public function addImage(string $Image): static
    {
        $images = $this->getImages();
        if (!in_array($Image, $images, true) && count($images) < 4) {
            $images[] = $Image;
            $this->Images = $images;
        }

        return $this;
    }

    public function removeImage(string $Image): static
    {
        $images = array_filter(
            $this->getImages(),
            static fn ($existing) => $existing !== $Image
        );

        $this->Images = !empty($images) ? array_values($images) : null;

        return $this;
    }

    public function getPrimaryImage(): ?string
    {
        $gallery = $this->getImages();
        if (!empty($gallery)) {
            return $gallery[0];
        }

        return $this->Image;
    }

    public function getTotalStockQuantity(): int
    {
        $total = 0;
        foreach ($this->stocks as $stock) {
            $total += $stock->getQuantity() ?? 0;
        }

        return $total;
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
