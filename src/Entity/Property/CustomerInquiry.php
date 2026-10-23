<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Rental\Lease;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\CustomerInquiryStatus;
use App\Enum\CustomerInquiryType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_inquiry', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_inquiry_converted_lease', columns: ['converted_lease_id'])])]
#[ORM\UniqueConstraint(name: 'uniq_inquiry_org_idempotency', columns: ['organization_id', 'idempotency_key_hash'])]
#[ORM\Index(name: 'idx_inquiry_org_status_created', columns: ['organization_id', 'status', 'created_at'])]
#[ORM\Index(name: 'idx_inquiry_listing', columns: ['listing_id', 'created_at'])]
#[ORM\Index(name: 'idx_inquiry_assignee', columns: ['assigned_to_id'])]
class CustomerInquiry extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Listing::class)]
    #[ORM\JoinColumn(name: 'listing_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Listing $listing;

    #[ORM\OneToOne(targetEntity: Lease::class)]
    #[ORM\JoinColumn(name: 'converted_lease_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Lease $convertedLease = null;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CustomerInquiryType::class)]
    private CustomerInquiryType $type;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $phone;

    #[ORM\Column(type: Types::STRING, length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 24, enumType: CustomerInquiryStatus::class)]
    private CustomerInquiryStatus $status = CustomerInquiryStatus::NEW;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'assigned_to_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $assignedTo = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $consentedAt;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $closedReason = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $idempotencyKeyHash;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $payloadHash;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getListing(): Listing { return $this->listing; }
    public function setListing(Listing $listing): static { $this->listing = $listing; return $this; }
    public function getConvertedLease(): ?Lease { return $this->convertedLease; }
    public function setConvertedLease(?Lease $lease): static { $this->convertedLease = $lease; return $this; }
    public function getType(): CustomerInquiryType { return $this->type; }
    public function setType(CustomerInquiryType $type): static { $this->type = $type; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = trim($name); return $this; }
    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): static { $this->phone = trim($phone); return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email === null ? null : trim($email); return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): static { $this->message = $message === null ? null : trim($message); return $this; }
    public function getStatus(): CustomerInquiryStatus { return $this->status; }
    public function setStatus(CustomerInquiryStatus $status): static { $this->status = $status; return $this; }
    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $user): static { $this->assignedTo = $user; return $this; }
    public function getConsentedAt(): \DateTimeImmutable { return $this->consentedAt; }
    public function setConsentedAt(\DateTimeImmutable $date): static { $this->consentedAt = $date; return $this; }
    public function getClosedReason(): ?string { return $this->closedReason; }
    public function setClosedReason(?string $reason): static { $this->closedReason = $reason === null ? null : trim($reason); return $this; }
    public function getIdempotencyKeyHash(): string { return $this->idempotencyKeyHash; }
    public function setIdempotencyKeyHash(string $hash): static { $this->idempotencyKeyHash = $hash; return $this; }
    public function getPayloadHash(): string { return $this->payloadHash; }
    public function setPayloadHash(string $hash): static { $this->payloadHash = $hash; return $this; }
}
