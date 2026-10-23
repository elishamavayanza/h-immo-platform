<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\User;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\Currency;
use App\Enum\ListingOfferType;
use App\Enum\ListingStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Annonce commerciale indépendante de l'unité et soumise à validation. */
#[ORM\Entity]
#[ORM\Table(name: 'property_listing')]
#[ORM\UniqueConstraint(name: 'uniq_property_listing_unit', columns: ['unit_id'])]
#[ORM\Index(name: 'idx_property_listing_status', columns: ['status', 'updated_at'])]
class Listing extends TimestampedEntity
{
    #[ORM\OneToOne(targetEntity: Unit::class, inversedBy: 'listing')]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Unit $unit;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ListingStatus::class)]
    private ListingStatus $status = ListingStatus::DRAFT;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: ListingOfferType::class)]
    private ListingOfferType $offerType;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $price;

    #[ORM\Column(type: Types::STRING, length: 3, enumType: Currency::class)]
    private Currency $currency;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** Instantané relu par le public : les changements ultérieurs du patrimoine ne contournent pas le contrôle éditorial. */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $publicSnapshot = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    public function getUnit(): Unit { return $this->unit; }
    public function setUnit(Unit $unit): static { $this->unit = $unit; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): static { $this->createdBy = $user; return $this; }
    public function getStatus(): ListingStatus { return $this->status; }
    public function setStatus(ListingStatus $status): static { $this->status = $status; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): static { $this->description = trim($description); return $this; }
    public function getOfferType(): ListingOfferType { return $this->offerType; }
    public function setOfferType(ListingOfferType $offerType): static { $this->offerType = $offerType; return $this; }
    public function getPrice(): string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }
    public function getCurrency(): Currency { return $this->currency; }
    public function setCurrency(Currency $currency): static { $this->currency = $currency; return $this; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $date): static { $this->publishedAt = $date; return $this; }
    public function getPublicSnapshot(): ?array { return $this->publicSnapshot; }
    public function setPublicSnapshot(?array $snapshot): static { $this->publicSnapshot = $snapshot; return $this; }
    public function getVersion(): int { return $this->version; }
}
