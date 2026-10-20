<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Property\Unit;
use App\Entity\Shared\SoftDeletableEntity;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleOverrideMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
#[ORM\UniqueConstraint(name: 'uniq_lease_id_org', columns: ['id', 'organization_id'])]
class Lease extends SoftDeletableEntity
{
    /** @var Collection<int, Unit> Espaces secondaires couverts par le même contrat. */
    #[ORM\ManyToMany(targetEntity: Unit::class)]
    #[ORM\JoinTable(
        name: 'lease_unit',
        joinColumns: [new ORM\JoinColumn(name: 'lease_id', referencedColumnName: 'id', onDelete: 'CASCADE')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'unit_id', referencedColumnName: 'id', onDelete: 'RESTRICT')],
    )]
    private Collection $additionalUnits;

    /** @var Collection<int, LeaseOccupant> Personnes autorisées occupant le logement. */
    #[ORM\OneToMany(mappedBy: 'lease', targetEntity: LeaseOccupant::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $occupants;

    public function __construct()
    {
        parent::__construct();
        $this->additionalUnits = new ArrayCollection();
        $this->occupants = new ArrayCollection();
    }

    /** @return Collection<int, Unit> */
    public function getAdditionalUnits(): Collection { return $this->additionalUnits; }

    public function addAdditionalUnit(Unit $unit): static
    {
        if ($unit !== $this->unit && !$this->additionalUnits->contains($unit)) $this->additionalUnits->add($unit);
        return $this;
    }

    /** @return Collection<int, LeaseOccupant> */
    public function getOccupants(): Collection { return $this->occupants; }

    public function addOccupant(LeaseOccupant $occupant): static
    {
        if (!$this->occupants->contains($occupant)) { $this->occupants->add($occupant); $occupant->setLease($this); }
        return $this;
    }

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
     * Taux de change de référence pour le bail (si différent de la devise de l'organisation).
     * Permet de convertir les loyers/paiements vers la devise de référence.
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8, nullable: true)]
    private ?string $exchangeRate = null;

    /**
     * Devise de référence pour la conversion (si exchangeRate non null).
     */
    #[ORM\Column(type: Types::STRING, enumType: Currency::class, nullable: true)]
    private ?Currency $referenceCurrency = null;

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

    /** Règles structurées du bail : l'héritage est un choix explicite par champ. */
    #[ORM\Column(name: 'due_mode_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $dueModeOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'due_mode_override_value', type: Types::STRING, length: 16, enumType: RentalPaymentMode::class, nullable: true)]
    private ?RentalPaymentMode $dueModeOverrideValue = null;

    #[ORM\Column(name: 'due_day_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $dueDayOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'due_day_override_value', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    #[Assert\Range(min: 1, max: 31)]
    private ?int $dueDayOverrideValue = null;

    #[ORM\Column(name: 'first_partial_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $firstPartialOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'first_partial_override_value', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class, nullable: true)]
    private ?PartialRentPolicy $firstPartialOverrideValue = null;

    #[ORM\Column(name: 'last_partial_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $lastPartialOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'last_partial_override_value', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class, nullable: true)]
    private ?PartialRentPolicy $lastPartialOverrideValue = null;

    #[ORM\Column(name: 'proration_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $prorationOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'proration_override_value', type: Types::STRING, length: 32, enumType: RentProrationMethod::class, nullable: true)]
    private ?RentProrationMethod $prorationOverrideValue = null;

    #[ORM\Column(name: 'rounding_override_mode', type: Types::STRING, length: 12, enumType: RentalRuleOverrideMode::class)]
    private RentalRuleOverrideMode $roundingOverrideMode = RentalRuleOverrideMode::INHERIT;

    #[ORM\Column(name: 'rounding_override_value', type: Types::STRING, length: 32, enumType: RentRoundingRule::class, nullable: true)]
    private ?RentRoundingRule $roundingOverrideValue = null;

    #[Assert\Callback]
    public function validateRentalRuleOverrides(ExecutionContextInterface $context): void
    {
        $pairs = [
            [$this->dueModeOverrideMode, 'dueModeOverrideValue', $this->dueModeOverrideValue],
            [$this->dueDayOverrideMode, 'dueDayOverrideValue', $this->dueDayOverrideValue],
            [$this->firstPartialOverrideMode, 'firstPartialOverrideValue', $this->firstPartialOverrideValue],
            [$this->lastPartialOverrideMode, 'lastPartialOverrideValue', $this->lastPartialOverrideValue],
            [$this->prorationOverrideMode, 'prorationOverrideValue', $this->prorationOverrideValue],
            [$this->roundingOverrideMode, 'roundingOverrideValue', $this->roundingOverrideValue],
        ];

        foreach ($pairs as [$mode, $valuePath, $value]) {
            $mustHaveValue = $mode === RentalRuleOverrideMode::OVERRIDE;
            if ($mustHaveValue === ($value === null)) {
                $message = $mustHaveValue
                    ? 'Une valeur est obligatoire lorsque la règle est remplacée.'
                    : 'Une valeur ne peut pas être fournie lorsque la règle est héritée.';
                $context->buildViolation($message)->atPath($valuePath)->addViolation();
            }
        }
    }

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

    public function getExchangeRate(): ?string
    {
        return $this->exchangeRate;
    }

    public function setExchangeRate(?string $exchangeRate): static
    {
        $this->exchangeRate = $exchangeRate;

        return $this;
    }

    public function getReferenceCurrency(): ?Currency
    {
        return $this->referenceCurrency;
    }

    public function setReferenceCurrency(?Currency $referenceCurrency): static
    {
        $this->referenceCurrency = $referenceCurrency;

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

    public function getDueModeOverrideMode(): RentalRuleOverrideMode { return $this->dueModeOverrideMode; }
    public function setDueModeOverrideMode(RentalRuleOverrideMode $mode): static { $this->dueModeOverrideMode = $mode; return $this; }
    public function getDueModeOverrideValue(): ?RentalPaymentMode { return $this->dueModeOverrideValue; }
    public function setDueModeOverrideValue(?RentalPaymentMode $value): static { $this->dueModeOverrideValue = $value; return $this; }
    public function getDueDayOverrideMode(): RentalRuleOverrideMode { return $this->dueDayOverrideMode; }
    public function setDueDayOverrideMode(RentalRuleOverrideMode $mode): static { $this->dueDayOverrideMode = $mode; return $this; }
    public function getDueDayOverrideValue(): ?int { return $this->dueDayOverrideValue; }
    public function setDueDayOverrideValue(?int $value): static { $this->dueDayOverrideValue = $value; return $this; }
    public function getFirstPartialOverrideMode(): RentalRuleOverrideMode { return $this->firstPartialOverrideMode; }
    public function setFirstPartialOverrideMode(RentalRuleOverrideMode $mode): static { $this->firstPartialOverrideMode = $mode; return $this; }
    public function getFirstPartialOverrideValue(): ?PartialRentPolicy { return $this->firstPartialOverrideValue; }
    public function setFirstPartialOverrideValue(?PartialRentPolicy $value): static { $this->firstPartialOverrideValue = $value; return $this; }
    public function getLastPartialOverrideMode(): RentalRuleOverrideMode { return $this->lastPartialOverrideMode; }
    public function setLastPartialOverrideMode(RentalRuleOverrideMode $mode): static { $this->lastPartialOverrideMode = $mode; return $this; }
    public function getLastPartialOverrideValue(): ?PartialRentPolicy { return $this->lastPartialOverrideValue; }
    public function setLastPartialOverrideValue(?PartialRentPolicy $value): static { $this->lastPartialOverrideValue = $value; return $this; }
    public function getProrationOverrideMode(): RentalRuleOverrideMode { return $this->prorationOverrideMode; }
    public function setProrationOverrideMode(RentalRuleOverrideMode $mode): static { $this->prorationOverrideMode = $mode; return $this; }
    public function getProrationOverrideValue(): ?RentProrationMethod { return $this->prorationOverrideValue; }
    public function setProrationOverrideValue(?RentProrationMethod $value): static { $this->prorationOverrideValue = $value; return $this; }
    public function getRoundingOverrideMode(): RentalRuleOverrideMode { return $this->roundingOverrideMode; }
    public function setRoundingOverrideMode(RentalRuleOverrideMode $mode): static { $this->roundingOverrideMode = $mode; return $this; }
    public function getRoundingOverrideValue(): ?RentRoundingRule { return $this->roundingOverrideValue; }
    public function setRoundingOverrideValue(?RentRoundingRule $value): static { $this->roundingOverrideValue = $value; return $this; }
}
