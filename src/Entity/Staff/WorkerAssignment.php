<?php

declare(strict_types=1);

namespace App\Entity\Staff;

use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Unit;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\Currency;
use App\Enum\WorkerRole;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * WorkerAssignment
 *
 * Package  : Staff Management
 * Table    : worker_assignment
 *
 * Affectation d'un Worker à un niveau du patrimoine, pour une durée et
 * une fonction données, avec la rémunération qui s'y rattache.
 *
 * Une affectation porte exactement un niveau de cible parmi `parcel`,
 * `building` et `unit`. `city` est toujours renseignée : elle désigne la
 * ville d'exercice et permet de borner l'affectation sans traverser la
 * chaîne du patrimoine, comme pour Expense. L'invariant
 * « au plus une des trois cibles est renseignée, et elle appartient à la
 * ville » est une règle métier : il est vérifié par les services, pas ici,
 * conformément à la règle « pas de logique dans les entités ».
 *
 * Les versements effectifs ne sont PAS portés par cette entité : ils sont
 * enregistrés dans Expense avec la catégorie SALARY, ce qui évite de dupliquer
 * un montant entre son taux contractuel et son règlement.
 */
#[ORM\Entity]
#[ORM\Table(name: 'worker_assignment')]
#[ORM\Index(name: 'idx_assignment_worker', columns: ['worker_id'])]
#[ORM\Index(name: 'idx_assignment_city', columns: ['city_id'])]
class WorkerAssignment extends SoftDeletableEntity
{
    /**
     * Travailleur affecté.
     */
    #[ORM\ManyToOne(targetEntity: Worker::class)]
    #[ORM\JoinColumn(name: 'worker_id', referencedColumnName: 'id', nullable: false)]
    private Worker $worker;

    /**
     * Ville d'exercice de l'affectation.
     */
    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(name: 'city_id', referencedColumnName: 'id', nullable: false)]
    private City $city;

    /**
     * Parcelle concernée, lorsqu'il s'agit d'une affectation à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: true)]
    private ?Parcel $parcel = null;

    /**
     * Immeuble concerné, lorsqu'il s'agit d'une affectation à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Building::class)]
    #[ORM\JoinColumn(name: 'building_id', referencedColumnName: 'id', nullable: true)]
    private ?Building $building = null;

    /**
     * Unité concernée, lorsqu'il s'agit d'une affectation à ce niveau.
     */
    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: true)]
    private ?Unit $unit = null;

    /**
     * Fonction exercée dans le cadre de cette affectation.
     */
    #[ORM\Column(type: Types::STRING, enumType: WorkerRole::class)]
    private WorkerRole $role;

    /**
     * Rémunération mensuelle de base attachée à cette affectation.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $monthlySalary;

    /**
     * Devise de la rémunération.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * Date de début de l'affectation.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    /**
     * Date de fin de l'affectation, ou null si elle est en cours.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    /**
     * Notes complémentaires sur l'affectation.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getWorker(): Worker
    {
        return $this->worker;
    }

    public function setWorker(Worker $worker): static
    {
        $this->worker = $worker;

        return $this;
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function setCity(City $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getParcel(): ?Parcel
    {
        return $this->parcel;
    }

    public function setParcel(?Parcel $parcel): static
    {
        $this->parcel = $parcel;

        return $this;
    }

    public function getBuilding(): ?Building
    {
        return $this->building;
    }

    public function setBuilding(?Building $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function getUnit(): ?Unit
    {
        return $this->unit;
    }

    public function setUnit(?Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getRole(): WorkerRole
    {
        return $this->role;
    }

    public function setRole(WorkerRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getMonthlySalary(): string
    {
        return $this->monthlySalary;
    }

    public function setMonthlySalary(string $monthlySalary): static
    {
        $this->monthlySalary = $monthlySalary;

        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }
}
