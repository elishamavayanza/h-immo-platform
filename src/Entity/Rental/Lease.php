<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Property\Unit;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lease
 *
 * Package  : Rental Management
 * Table    : lease
 *
 * Contrat de bail liant un Tenant à une Unit, pour une Organization
 * donnée. `reference` est unique par Organization.
 *
 * Règle métier (garantie au niveau applicatif, cf. documentation) :
 * une même Unit ne doit jamais avoir plus d'un Lease au statut
 * LeaseStatus::ACTIVE simultanément. Cette contrainte n'est PAS
 * codée ici (l'entité reste dépourvue de logique métier) : elle doit
 * être appliquée par un service dédié (ex. LeaseActivationService)
 * lors de la création/activation d'un bail.
 */
#[ORM\Entity]
#[ORM\Table(name: 'lease')]
#[ORM\UniqueConstraint(name: 'uniq_lease_org_reference', columns: ['organization_id', 'reference'])]
class Lease extends SoftDeletableEntity
{
    /**
     * Organisation propriétaire du contrat de bail.
     */
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false)]
    private Organization $organization;

    /**
     * Locataire concerné par le contrat.
     */
    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false)]
    private Tenant $tenant;

    /**
     * Unité concernée par le contrat de bail.
     */
    #[ORM\ManyToOne(targetEntity: Unit::class)]
    #[ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', nullable: false)]
    private Unit $unit;

    /**
     * Référence unique du contrat dans l'organisation.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $reference;

    /**
     * Date de début du contrat.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    /**
     * Date prévue de fin du contrat.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    /**
     * Montant du loyer mensuel prévu au contrat.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $monthlyRent;

    /**
     * Montant de la garantie locative.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $depositAmount = null;

    /**
     * Devise utilisée pour les montants du contrat.
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class)]
    private Currency $currency;

    /**
     * État actuel du contrat de bail.
     */
    #[ORM\Column(type: Types::STRING, enumType: LeaseStatus::class)]
    private LeaseStatus $status = LeaseStatus::DRAFT;

    /**
     * Date effective de résiliation du contrat.
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $terminationDate = null;

    /**
     * Motif de résiliation du contrat.
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $terminationReason = null;

    /**
     * Notes complémentaires liées au contrat.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Clauses contractuelles et conditions particulières du bail.
     *
     * Ce champ contient les termes et conditions contractuels détaillés
     * (ex: indexation, travaux, sous-location, assurance, etc.)
     * conformément au modèle LOOP validé.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $terms = null;

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function setOrganization(Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function setTenant(Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function getUnit(): Unit
    {
        return $this->unit;
    }

    public function setUnit(Unit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getMonthlyRent(): string
    {
        return $this->monthlyRent;
    }

    public function setMonthlyRent(string $monthlyRent): static
    {
        $this->monthlyRent = $monthlyRent;

        return $this;
    }

    public function getDepositAmount(): ?string
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?string $depositAmount): static
    {
        $this->depositAmount = $depositAmount;

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

    public function getStatus(): LeaseStatus
    {
        return $this->status;
    }

    public function setStatus(LeaseStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTerminationDate(): ?\DateTimeImmutable
    {
        return $this->terminationDate;
    }

    public function setTerminationDate(?\DateTimeImmutable $terminationDate): static
    {
        $this->terminationDate = $terminationDate;

        return $this;
    }

    public function getTerminationReason(): ?string
    {
        return $this->terminationReason;
    }

    public function setTerminationReason(?string $terminationReason): static
    {
        $this->terminationReason = $terminationReason;

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

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): static
    {
        $this->terms = $terms;

        return $this;
    }
}
