<?php

declare(strict_types=1);

namespace App\Entity\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleVersion;
use App\Entity\Shared\CreatedOnlyEntity;
use App\Enum\Currency;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleSource;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Valeurs et provenance utilisées lors de la création d'une échéance. */
#[ORM\Entity]
#[ORM\Table(name: 'rent_rule_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_rent_rule_snapshot_rent', columns: ['rent_id'])]
final class RentRuleSnapshot extends CreatedOnlyEntity
{
    #[ORM\OneToOne(targetEntity: Rent::class, inversedBy: 'ruleSnapshot')]
    #[ORM\JoinColumn(name: 'rent_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Rent $rent = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Organization $organization = null;

    /** Référence explicite pour retrouver la version et garantir sa cohérence en base. */
    #[ORM\ManyToOne(targetEntity: OrganizationRentalRuleVersion::class)]
    #[ORM\JoinColumn(name: 'rule_version_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?OrganizationRentalRuleVersion $ruleVersion = null;

    #[ORM\Column(name: 'calculator_version', type: Types::STRING, length: 40)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 40)]
    private string $calculatorVersion = '';

    #[ORM\Column(type: Types::STRING, length: 3, enumType: Currency::class)]
    #[Assert\NotNull]
    private ?Currency $currency = null;

    #[ORM\Column(name: 'due_mode_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $dueModeSource = null;

    #[ORM\Column(name: 'due_mode', type: Types::STRING, length: 16, enumType: RentalPaymentMode::class)]
    #[Assert\NotNull]
    private ?RentalPaymentMode $dueMode = null;

    #[ORM\Column(name: 'due_day_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $dueDaySource = null;

    #[ORM\Column(name: 'due_day', type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 1, max: 31)]
    private int $dueDay = 5;

    #[ORM\Column(name: 'first_partial_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $firstPartialSource = null;

    #[ORM\Column(name: 'first_partial_policy', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class)]
    #[Assert\NotNull]
    private ?PartialRentPolicy $firstPartialPolicy = null;

    #[ORM\Column(name: 'last_partial_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $lastPartialSource = null;

    #[ORM\Column(name: 'last_partial_policy', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class)]
    #[Assert\NotNull]
    private ?PartialRentPolicy $lastPartialPolicy = null;

    #[ORM\Column(name: 'proration_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $prorationSource = null;

    #[ORM\Column(name: 'proration_method', type: Types::STRING, length: 32, enumType: RentProrationMethod::class)]
    #[Assert\NotNull]
    private ?RentProrationMethod $prorationMethod = null;

    #[ORM\Column(name: 'rounding_source', type: Types::STRING, length: 24, enumType: RentalRuleSource::class)]
    #[Assert\NotNull]
    private ?RentalRuleSource $roundingSource = null;

    #[ORM\Column(name: 'rounding_rule', type: Types::STRING, length: 32, enumType: RentRoundingRule::class)]
    #[Assert\NotNull]
    private ?RentRoundingRule $roundingRule = null;

    #[ORM\Column(name: 'base_monthly_rent', type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/')]
    #[Assert\PositiveOrZero]
    private string $baseMonthlyRent = '0.00';

    #[ORM\Column(name: 'covered_start', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $coveredStart = null;

    #[ORM\Column(name: 'covered_end', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $coveredEnd = null;

    #[ORM\Column(name: 'covered_days', type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 1, max: 31)]
    private int $coveredDays = 0;

    #[ORM\Column(name: 'days_in_month', type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 28, max: 31)]
    private int $daysInMonth = 0;

    #[Assert\Callback]
    public function validateSnapshot(ExecutionContextInterface $context): void
    {
        if ($this->coveredStart !== null && $this->coveredEnd !== null) {
            if ($this->coveredStart > $this->coveredEnd) {
                $context->buildViolation('Le début de couverture doit précéder sa fin.')
                    ->atPath('coveredEnd')
                    ->addViolation();
            } elseif ($this->coveredDays !== ((int) $this->coveredStart->diff($this->coveredEnd)->days + 1)) {
                $context->buildViolation('Le nombre de jours couverts doit correspondre aux bornes inclusives.')
                    ->atPath('coveredDays')
                    ->addViolation();
            }
        }

        if ($this->coveredDays > $this->daysInMonth) {
            $context->buildViolation('Les jours couverts ne peuvent pas dépasser les jours du mois.')
                ->atPath('coveredDays')
                ->addViolation();
        }

        $sources = [
            $this->dueModeSource,
            $this->dueDaySource,
            $this->firstPartialSource,
            $this->lastPartialSource,
            $this->prorationSource,
            $this->roundingSource,
        ];
        $usesOrganizationVersion = in_array(RentalRuleSource::ORGANIZATION_VERSION, $sources, true);
        if ($usesOrganizationVersion !== ($this->ruleVersion !== null)) {
            $context->buildViolation('La référence à une version d’organisation doit correspondre aux provenances du snapshot.')
                ->atPath('ruleVersion')
                ->addViolation();
        }

        if ($this->rent !== null && $this->organization !== null && $this->rent->getOrganization() !== $this->organization) {
            $context->buildViolation('Le snapshot et l’échéance doivent appartenir à la même organisation.')
                ->atPath('organization')
                ->addViolation();
        }

        if ($this->rent !== null && $this->ruleVersion !== $this->rent->getRuleVersion()) {
            $context->buildViolation('La version du snapshot doit être celle référencée par l’échéance.')
                ->atPath('ruleVersion')
                ->addViolation();
        }
    }

    public function getRent(): ?Rent { return $this->rent; }
    public function setRent(Rent $rent): static { $this->rent = $rent; return $this; }
    public function getOrganization(): ?Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getRuleVersion(): ?OrganizationRentalRuleVersion { return $this->ruleVersion; }
    public function setRuleVersion(?OrganizationRentalRuleVersion $version): static { $this->ruleVersion = $version; return $this; }
    public function getCalculatorVersion(): string { return $this->calculatorVersion; }
    public function setCalculatorVersion(string $version): static { $this->calculatorVersion = $version; return $this; }
    public function getCurrency(): ?Currency { return $this->currency; }
    public function setCurrency(Currency $currency): static { $this->currency = $currency; return $this; }
    public function getDueModeSource(): ?RentalRuleSource { return $this->dueModeSource; }
    public function setDueModeSource(RentalRuleSource $source): static { $this->dueModeSource = $source; return $this; }
    public function getDueMode(): ?RentalPaymentMode { return $this->dueMode; }
    public function setDueMode(RentalPaymentMode $mode): static { $this->dueMode = $mode; return $this; }
    public function getDueDaySource(): ?RentalRuleSource { return $this->dueDaySource; }
    public function setDueDaySource(RentalRuleSource $source): static { $this->dueDaySource = $source; return $this; }
    public function getDueDay(): int { return $this->dueDay; }
    public function setDueDay(int $day): static { $this->dueDay = $day; return $this; }
    public function getFirstPartialSource(): ?RentalRuleSource { return $this->firstPartialSource; }
    public function setFirstPartialSource(RentalRuleSource $source): static { $this->firstPartialSource = $source; return $this; }
    public function getFirstPartialPolicy(): ?PartialRentPolicy { return $this->firstPartialPolicy; }
    public function setFirstPartialPolicy(PartialRentPolicy $policy): static { $this->firstPartialPolicy = $policy; return $this; }
    public function getLastPartialSource(): ?RentalRuleSource { return $this->lastPartialSource; }
    public function setLastPartialSource(RentalRuleSource $source): static { $this->lastPartialSource = $source; return $this; }
    public function getLastPartialPolicy(): ?PartialRentPolicy { return $this->lastPartialPolicy; }
    public function setLastPartialPolicy(PartialRentPolicy $policy): static { $this->lastPartialPolicy = $policy; return $this; }
    public function getProrationSource(): ?RentalRuleSource { return $this->prorationSource; }
    public function setProrationSource(RentalRuleSource $source): static { $this->prorationSource = $source; return $this; }
    public function getProrationMethod(): ?RentProrationMethod { return $this->prorationMethod; }
    public function setProrationMethod(RentProrationMethod $method): static { $this->prorationMethod = $method; return $this; }
    public function getRoundingSource(): ?RentalRuleSource { return $this->roundingSource; }
    public function setRoundingSource(RentalRuleSource $source): static { $this->roundingSource = $source; return $this; }
    public function getRoundingRule(): ?RentRoundingRule { return $this->roundingRule; }
    public function setRoundingRule(RentRoundingRule $rule): static { $this->roundingRule = $rule; return $this; }
    public function getBaseMonthlyRent(): string { return $this->baseMonthlyRent; }
    public function setBaseMonthlyRent(string $amount): static { $this->baseMonthlyRent = $amount; return $this; }
    public function getCoveredStart(): ?\DateTimeImmutable { return $this->coveredStart; }
    public function setCoveredStart(\DateTimeImmutable $date): static { $this->coveredStart = $date; return $this; }
    public function getCoveredEnd(): ?\DateTimeImmutable { return $this->coveredEnd; }
    public function setCoveredEnd(\DateTimeImmutable $date): static { $this->coveredEnd = $date; return $this; }
    public function getCoveredDays(): int { return $this->coveredDays; }
    public function setCoveredDays(int $days): static { $this->coveredDays = $days; return $this; }
    public function getDaysInMonth(): int { return $this->daysInMonth; }
    public function setDaysInMonth(int $days): static { $this->daysInMonth = $days; return $this; }
}
