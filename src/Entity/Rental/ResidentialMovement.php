<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\Unit;
use App\Entity\Shared\CreatedOnlyEntity;
use App\Enum\ResidentialMovementStatus;
use App\Enum\ResidentialMovementType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Événement résidentiel dont seules les corrections contrôlées sont permises. */
#[ORM\Entity]
#[ORM\Table(name: 'residential_movement')]
#[ORM\Index(name: 'idx_res_move_tenant_date', columns: ['tenant_id', 'effective_date'])]
#[ORM\Index(name: 'idx_res_move_org_status', columns: ['organization_id', 'status'])]
#[ORM\Index(name: 'idx_res_move_lease', columns: ['lease_id'])]
#[ORM\Index(name: 'idx_res_move_previous_unit', columns: ['previous_unit_id'])]
#[ORM\Index(name: 'idx_res_move_next_unit', columns: ['next_unit_id'])]
#[ORM\Index(name: 'idx_res_move_recorded_by', columns: ['recorded_by_id'])]
class ResidentialMovement extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false)]
    private Tenant $tenant;

    #[ORM\ManyToOne(targetEntity: Lease::class)]
    #[ORM\JoinColumn(name: 'lease_id', referencedColumnName: 'id', nullable: true)]
    private ?Lease $lease = null;

    #[ORM\ManyToOne(targetEntity: Lease::class)]
    #[ORM\JoinColumn(name: 'next_lease_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Lease $nextLease = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'previous_unit_id', referencedColumnName: 'id', nullable: true)]
    private ?Unit $previousUnit = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'next_unit_id', referencedColumnName: 'id', nullable: true)]
    private ?Unit $nextUnit = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recorded_by_id', referencedColumnName: 'id', nullable: false)]
    private User $recordedBy;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: ResidentialMovementType::class)]
    private ResidentialMovementType $type;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ResidentialMovementStatus::class)]
    private ResidentialMovementStatus $status;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $plannedDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveDate = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $effectiveDateEstimated = false;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $correctionReason = null;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $tenant): static { $this->tenant = $tenant; return $this; }
    public function getLease(): ?Lease { return $this->lease; }
    public function setLease(?Lease $lease): static { $this->lease = $lease; return $this; }
    public function getNextLease(): ?Lease { return $this->nextLease; }
    public function setNextLease(?Lease $lease): static { $this->nextLease = $lease; return $this; }
    public function getPreviousUnit(): ?Unit { return $this->previousUnit; }
    public function setPreviousUnit(?Unit $unit): static { $this->previousUnit = $unit; return $this; }
    public function getNextUnit(): ?Unit { return $this->nextUnit; }
    public function setNextUnit(?Unit $unit): static { $this->nextUnit = $unit; return $this; }
    public function getRecordedBy(): User { return $this->recordedBy; }
    public function setRecordedBy(User $user): static { $this->recordedBy = $user; return $this; }
    public function getType(): ResidentialMovementType { return $this->type; }
    public function setType(ResidentialMovementType $type): static { $this->type = $type; return $this; }
    public function getStatus(): ResidentialMovementStatus { return $this->status; }
    public function setStatus(ResidentialMovementStatus $status): static { $this->status = $status; return $this; }
    public function getPlannedDate(): ?\DateTimeImmutable { return $this->plannedDate; }
    public function setPlannedDate(?\DateTimeImmutable $date): static { $this->plannedDate = $date; return $this; }
    public function getEffectiveDate(): ?\DateTimeImmutable { return $this->effectiveDate; }
    public function setEffectiveDate(?\DateTimeImmutable $date): static { $this->effectiveDate = $date; return $this; }
    public function isEffectiveDateEstimated(): bool { return $this->effectiveDateEstimated; }
    public function setEffectiveDateEstimated(bool $estimated): static { $this->effectiveDateEstimated = $estimated; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): static { $this->details = $details; return $this; }
    public function getCorrectionReason(): ?string { return $this->correctionReason; }
    public function setCorrectionReason(?string $reason): static { $this->correctionReason = $reason; return $this; }
}
