<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\TimestampedEntity;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Brouillon modifiable séparé des versions publiées et consommées par le résolveur. */
#[ORM\Entity]
#[ORM\Table(name: 'organization_rental_rule_draft')]
#[ORM\Index(name: 'idx_rental_rule_draft_org', columns: ['organization_id', 'created_at'])]
final class OrganizationRentalRuleDraft extends TimestampedEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\ManyToOne(targetEntity: OrganizationRentalRuleVersion::class)]
    #[ORM\JoinColumn(name: 'published_version_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?OrganizationRentalRuleVersion $publishedVersion = null;

    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(name: 'due_mode', type: Types::STRING, length: 16, enumType: RentalPaymentMode::class)]
    private RentalPaymentMode $dueMode = RentalPaymentMode::IN_ADVANCE;
    #[ORM\Column(name: 'due_day', type: Types::SMALLINT, options: ['unsigned' => true])]
    #[Assert\Range(min: 1, max: 31)]
    private int $dueDay = 5;
    #[ORM\Column(name: 'first_partial_policy', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class)]
    private PartialRentPolicy $firstPartialPolicy = PartialRentPolicy::CLAMP_REGULAR_DUE_DAY;
    #[ORM\Column(name: 'last_partial_policy', type: Types::STRING, length: 32, enumType: PartialRentPolicy::class)]
    private PartialRentPolicy $lastPartialPolicy = PartialRentPolicy::CLAMP_REGULAR_DUE_DAY;
    #[ORM\Column(name: 'proration_method', type: Types::STRING, length: 32, enumType: RentProrationMethod::class)]
    private RentProrationMethod $prorationMethod = RentProrationMethod::ACTUAL_CALENDAR_DAYS;
    #[ORM\Column(name: 'rounding_rule', type: Types::STRING, length: 32, enumType: RentRoundingRule::class)]
    private RentRoundingRule $roundingRule = RentRoundingRule::USD_HALF_UP_CENT;
    #[ORM\Column(name: 'change_reason', type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $changeReason = '';

    #[Assert\Callback]
    public function validateEffectiveDate(ExecutionContextInterface $context): void
    {
        if ($this->effectiveFrom !== null && $this->effectiveFrom->format('d') !== '01') {
            $context->buildViolation('La date d’effet doit être le premier jour d’un mois.')
                ->atPath('effectiveFrom')->addViolation();
        }
    }

    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $user): static { $this->createdBy = $user; return $this; }
    public function getPublishedVersion(): ?OrganizationRentalRuleVersion { return $this->publishedVersion; }
    public function setPublishedVersion(?OrganizationRentalRuleVersion $version): static { $this->publishedVersion = $version; return $this; }
    public function getEffectiveFrom(): ?\DateTimeImmutable { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeImmutable $date): static { $this->effectiveFrom = $date; return $this; }
    public function getDueMode(): RentalPaymentMode { return $this->dueMode; }
    public function setDueMode(RentalPaymentMode $value): static { $this->dueMode = $value; return $this; }
    public function getDueDay(): int { return $this->dueDay; }
    public function setDueDay(int $value): static { $this->dueDay = $value; return $this; }
    public function getFirstPartialPolicy(): PartialRentPolicy { return $this->firstPartialPolicy; }
    public function setFirstPartialPolicy(PartialRentPolicy $value): static { $this->firstPartialPolicy = $value; return $this; }
    public function getLastPartialPolicy(): PartialRentPolicy { return $this->lastPartialPolicy; }
    public function setLastPartialPolicy(PartialRentPolicy $value): static { $this->lastPartialPolicy = $value; return $this; }
    public function getProrationMethod(): RentProrationMethod { return $this->prorationMethod; }
    public function setProrationMethod(RentProrationMethod $value): static { $this->prorationMethod = $value; return $this; }
    public function getRoundingRule(): RentRoundingRule { return $this->roundingRule; }
    public function setRoundingRule(RentRoundingRule $value): static { $this->roundingRule = $value; return $this; }
    public function getChangeReason(): string { return $this->changeReason; }
    public function setChangeReason(string $value): static { $this->changeReason = $value; return $this; }
}
