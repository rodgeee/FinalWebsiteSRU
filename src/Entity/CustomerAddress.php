<?php

namespace App\Entity;

use App\Repository\CustomerAddressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerAddressRepository::class)]
#[ORM\Table(name: 'customer_address')]
class CustomerAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    private ?string $label = 'Home';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $addressLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $city = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $province = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120)]
    private string $country = 'Philippines';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

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

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getAddressLine1(): ?string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): static
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getProvince(): ?string
    {
        return $this->province;
    }

    public function setProvince(?string $province): static
    {
        $this->province = $province;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDisplayLine1(): string
    {
        $parts = array_filter([
            trim((string) $this->addressLine1),
            trim((string) ($this->addressLine2 ?? '')),
            trim((string) $this->city),
            trim((string) ($this->postalCode ?? '')),
            trim((string) ($this->province ?? '')),
            trim($this->country),
        ]);

        return implode(', ', $parts);
    }

    public function getDisplayLine2(): string
    {
        $email = trim((string) ($this->contactEmail ?? ''));
        $phone = trim((string) ($this->contactPhone ?? ''));

        if ($email !== '' && $phone !== '') {
            return $email . ' | ' . $phone;
        }

        return $email !== '' ? $email : $phone;
    }
}
