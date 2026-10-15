<?php

declare(strict_types=1);

namespace App\Entity\System;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\DocumentCategory;
use App\Enum\DocumentEntityType;
use App\Repository\System\PrivateDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrivateDocumentRepository::class)]
#[ORM\Table(name: 'private_document')]
#[ORM\Index(name: 'idx_private_document_org_created', columns: ['organization_id', 'created_at'])]
#[ORM\Index(name: 'idx_private_document_city_created', columns: ['city_id', 'created_at'])]
#[ORM\Index(name: 'idx_private_document_related', columns: ['related_type', 'related_uuid'])]
class PrivateDocument extends SoftDeletableEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?City $city = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $uploadedBy;

    #[ORM\Column(type: Types::STRING, enumType: DocumentCategory::class, length: 32)]
    private DocumentCategory $category;

    #[ORM\Column(name: 'related_type', type: Types::STRING, enumType: DocumentEntityType::class, length: 24)]
    private DocumentEntityType $relatedType;

    #[ORM\Column(name: 'related_uuid', type: Types::STRING, length: 36)]
    private string $relatedUuid;

    #[ORM\Column(name: 'original_name', type: Types::STRING, length: 180)]
    private string $originalName;

    #[ORM\Column(name: 'storage_name', type: Types::STRING, length: 80, unique: true)]
    private string $storageName;

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 100)]
    private string $mimeType;

    #[ORM\Column(name: 'file_size', type: Types::INTEGER)]
    private int $fileSize;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $description = null;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getCity(): ?City { return $this->city; }
    public function setCity(?City $city): static { $this->city = $city; return $this; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function setUploadedBy(User $user): static { $this->uploadedBy = $user; return $this; }
    public function getCategory(): DocumentCategory { return $this->category; }
    public function setCategory(DocumentCategory $category): static { $this->category = $category; return $this; }
    public function getRelatedType(): DocumentEntityType { return $this->relatedType; }
    public function setRelatedType(DocumentEntityType $type): static { $this->relatedType = $type; return $this; }
    public function getRelatedUuid(): string { return $this->relatedUuid; }
    public function setRelatedUuid(string $uuid): static { $this->relatedUuid = $uuid; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $name): static { $this->originalName = $name; return $this; }
    public function getStorageName(): string { return $this->storageName; }
    public function setStorageName(string $name): static { $this->storageName = $name; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): int { return $this->fileSize; }
    public function setFileSize(int $size): static { $this->fileSize = $size; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
}
