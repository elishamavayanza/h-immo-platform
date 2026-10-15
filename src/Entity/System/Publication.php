<?php

declare(strict_types=1);

namespace App\Entity\System;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\PublicationAudience;
use App\Enum\PublicationCategory;
use App\Enum\PublicationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'publication')]
#[ORM\Index(name: 'idx_publication_status_date', columns: ['status', 'published_at'])]
class Publication extends TimestampedEntity
{
    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $title;

    /** @var list<array{type: string, text: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $contentBlocks = [];

    #[ORM\Column(type: Types::STRING, enumType: PublicationCategory::class)]
    private PublicationCategory $category;

    #[ORM\Column(type: Types::STRING, enumType: PublicationAudience::class)]
    private PublicationAudience $audience = PublicationAudience::ALL;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $audienceRoles = [];

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(type: Types::STRING, enumType: PublicationStatus::class)]
    private PublicationStatus $status = PublicationStatus::DRAFT;

    /** @var list<array{path: string, caption: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $images = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getContentBlocks(): array { return $this->contentBlocks; }
    public function setContentBlocks(array $blocks): static { $this->contentBlocks = $blocks; return $this; }
    public function getCategory(): PublicationCategory { return $this->category; }
    public function setCategory(PublicationCategory $category): static { $this->category = $category; return $this; }
    public function getAudience(): PublicationAudience { return $this->audience; }
    public function setAudience(PublicationAudience $audience): static { $this->audience = $audience; return $this; }
    public function getAudienceRoles(): array { return $this->audienceRoles; }
    public function setAudienceRoles(array $roles): static { $this->audienceRoles = $roles; return $this; }
    public function getOrganization(): ?Organization { return $this->organization; }
    public function setOrganization(?Organization $organization): static { $this->organization = $organization; return $this; }
    public function getScheduledAt(): ?\DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeImmutable $date): static { $this->scheduledAt = $date; return $this; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $date): static { $this->publishedAt = $date; return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $date): static { $this->endsAt = $date; return $this; }
    public function getStatus(): PublicationStatus { return $this->status; }
    public function setStatus(PublicationStatus $status): static { $this->status = $status; return $this; }
    public function getImages(): array { return $this->images; }
    public function setImages(array $images): static { $this->images = $images; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): static { $this->createdBy = $user; return $this; }
}
