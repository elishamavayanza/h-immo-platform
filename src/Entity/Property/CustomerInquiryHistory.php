<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\User;
use App\Entity\Identity\Organization;
use App\Entity\Shared\CreatedOnlyEntity;
use App\Enum\CustomerInquiryStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_inquiry_history')]
#[ORM\Index(name: 'idx_inquiry_history_organization', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_inquiry_history_date', columns: ['inquiry_id', 'created_at'])]
#[ORM\Index(name: 'idx_inquiry_history_actor', columns: ['actor_id'])]
class CustomerInquiryHistory extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: CustomerInquiry::class)]
    #[ORM\JoinColumn(name: 'inquiry_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private CustomerInquiry $inquiry;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?User $actor = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 24, nullable: true, enumType: CustomerInquiryStatus::class)]
    private ?CustomerInquiryStatus $oldStatus = null;

    #[ORM\Column(type: Types::STRING, length: 24, enumType: CustomerInquiryStatus::class)]
    private CustomerInquiryStatus $newStatus;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    public function getInquiry(): CustomerInquiry { return $this->inquiry; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function setInquiry(CustomerInquiry $inquiry): static { $this->inquiry = $inquiry; return $this; }
    public function getActor(): ?User { return $this->actor; }
    public function setActor(?User $actor): static { $this->actor = $actor; return $this; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getOldStatus(): ?CustomerInquiryStatus { return $this->oldStatus; }
    public function setOldStatus(?CustomerInquiryStatus $status): static { $this->oldStatus = $status; return $this; }
    public function getNewStatus(): CustomerInquiryStatus { return $this->newStatus; }
    public function setNewStatus(CustomerInquiryStatus $status): static { $this->newStatus = $status; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason === null ? null : trim($reason); return $this; }
}
