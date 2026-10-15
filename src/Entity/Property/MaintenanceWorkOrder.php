<?php

declare(strict_types=1);

namespace App\Entity\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Shared\SoftDeletableEntity;
use App\Entity\Staff\WorkerAssignment;
use App\Enum\MaintenancePriority;
use App\Enum\MaintenanceStatus;
use App\Repository\Property\MaintenanceWorkOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MaintenanceWorkOrderRepository::class)]
#[ORM\Table(name: 'maintenance_work_order')]
#[ORM\Index(name: 'idx_maintenance_city_status', columns: ['city_id', 'status'])]
#[ORM\Index(name: 'idx_maintenance_org_created', columns: ['organization_id', 'created_at'])]
class MaintenanceWorkOrder extends SoftDeletableEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private City $city;

    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Parcel $parcel = null;

    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Building $building = null;

    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Unit $unit = null;

    #[ORM\ManyToOne(targetEntity: WorkerAssignment::class)]
    #[ORM\JoinColumn(name: 'worker_assignment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?WorkerAssignment $workerAssignment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::STRING, enumType: MaintenancePriority::class, length: 12)]
    private MaintenancePriority $priority = MaintenancePriority::NORMAL;

    #[ORM\Column(type: Types::STRING, enumType: MaintenanceStatus::class, length: 16)]
    private MaintenanceStatus $status = MaintenanceStatus::OPEN;

    #[ORM\Column(name: 'reported_at', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $reportedAt;

    #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $value): static { $this->organization = $value; return $this; }
    public function getCity(): City { return $this->city; }
    public function setCity(City $value): static { $this->city = $value; return $this; }
    public function getParcel(): ?Parcel { return $this->parcel; }
    public function setParcel(?Parcel $value): static { $this->parcel = $value; return $this; }
    public function getBuilding(): ?Building { return $this->building; }
    public function setBuilding(?Building $value): static { $this->building = $value; return $this; }
    public function getUnit(): ?Unit { return $this->unit; }
    public function setUnit(?Unit $value): static { $this->unit = $value; return $this; }
    public function getWorkerAssignment(): ?WorkerAssignment { return $this->workerAssignment; }
    public function setWorkerAssignment(?WorkerAssignment $value): static { $this->workerAssignment = $value; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $value): static { $this->createdBy = $value; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $value): static { $this->title = $value; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $value): static { $this->description = $value; return $this; }
    public function getPriority(): MaintenancePriority { return $this->priority; }
    public function setPriority(MaintenancePriority $value): static { $this->priority = $value; return $this; }
    public function getStatus(): MaintenanceStatus { return $this->status; }
    public function setStatus(MaintenanceStatus $value): static { $this->status = $value; return $this; }
    public function getReportedAt(): \DateTimeImmutable { return $this->reportedAt; }
    public function setReportedAt(\DateTimeImmutable $value): static { $this->reportedAt = $value; return $this; }
    public function getDueDate(): ?\DateTimeImmutable { return $this->dueDate; }
    public function setDueDate(?\DateTimeImmutable $value): static { $this->dueDate = $value; return $this; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $value): static { $this->completedAt = $value; return $this; }
}
