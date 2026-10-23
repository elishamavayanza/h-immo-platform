<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Shared\TimestampedEntity;
use App\Enum\CustomerVisitStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_visit')]
#[ORM\Index(name: 'idx_customer_visit_org_status_date', columns: ['organization_id', 'status', 'scheduled_at'])]
#[ORM\Index(name: 'idx_customer_visit_inquiry_date', columns: ['inquiry_id', 'scheduled_at'])]
#[ORM\Index(name: 'idx_customer_visit_created_by', columns: ['created_by_id'])]
class CustomerVisit extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: CustomerInquiry::class)]
    #[ORM\JoinColumn(name: 'inquiry_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private CustomerInquiry $inquiry;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CustomerVisitStatus::class)]
    private CustomerVisitStatus $status = CustomerVisitStatus::PROPOSED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $outcome = null;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getInquiry(): CustomerInquiry { return $this->inquiry; }
    public function setInquiry(CustomerInquiry $inquiry): static { $this->inquiry = $inquiry; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): static { $this->createdBy = $user; return $this; }
    public function getScheduledAt(): \DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeImmutable $date): static { $this->scheduledAt = $date; return $this; }
    public function getStatus(): CustomerVisitStatus { return $this->status; }
    public function setStatus(CustomerVisitStatus $status): static { $this->status = $status; return $this; }
    public function getOutcome(): ?string { return $this->outcome; }
    public function setOutcome(?string $outcome): static { $this->outcome = $outcome === null ? null : trim($outcome); return $this; }
}
