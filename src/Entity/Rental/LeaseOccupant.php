<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Shared\CreatedOnlyEntity;
use App\Entity\Identity\Organization;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Historique des cotitulaires et occupants qui ne sont pas le titulaire principal. */
#[ORM\Entity]
#[ORM\Table(name: 'lease_occupant')]
#[ORM\Index(name: 'idx_lease_occupant_tenant', columns: ['lease_id', 'tenant_id'])]
class LeaseOccupant extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Lease::class, inversedBy: 'occupants')]
    #[ORM\JoinColumn(name: 'lease_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Lease $lease;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Tenant $tenant;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $role = 'occupant';

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    public function getLease(): Lease { return $this->lease; }
    public function setLease(Lease $lease): static { $this->lease = $lease; return $this; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function setTenant(Tenant $tenant): static { $this->tenant = $tenant; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getStartDate(): \DateTimeImmutable { return $this->startDate; }
    public function setStartDate(\DateTimeImmutable $date): static { $this->startDate = $date; return $this; }
    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $date): static { $this->endDate = $date; return $this; }
}
