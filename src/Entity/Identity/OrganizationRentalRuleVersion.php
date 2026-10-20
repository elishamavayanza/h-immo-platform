<?php

declare(strict_types=1);

namespace App\Entity\Identity;

use App\Entity\Shared\CreatedOnlyEntity;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'organization_rental_rule_version')]
#[ORM\UniqueConstraint(name: 'uniq_rental_rule_org_version', columns: ['organization_id', 'version_number'])]
#[ORM\UniqueConstraint(name: 'uniq_rental_rule_org_effective', columns: ['organization_id', 'effective_from'])]
#[ORM\UniqueConstraint(name: 'uniq_rental_rule_id_org', columns: ['id', 'organization_id'])]
#[ORM\Index(name: 'idx_rental_rule_org_effective', columns: ['organization_id', 'effective_from'])]
final class OrganizationRentalRuleVersion extends CreatedOnlyEntity
{
    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(name: 'organization_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Organization $organization = null;

    #[ORM\Column(name: 'version_number', type: Types::INTEGER, options: ['unsigned' => true])]
    #[Assert\Positive]
    private int $versionNumber = 0;

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

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'published_by', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?User $publishedBy = null;

    #[ORM\Column(name: 'publication_reason', type: Types::STRING, length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $publicationReason = '';

    /** La ligne n'existe qu'après publication; CreatedOnlyEntity::createdAt est l'horodatage de publication. */
    #[Assert\Callback]
    public function validateEffectiveDate(ExecutionContextInterface $context): void
    {
        if ($this->effectiveFrom !== null && $this->effectiveFrom->format('d') !== '01') {
            $context->buildViolation('La date d’effet doit être le premier jour d’un mois.')
                ->atPath('effectiveFrom')
                ->addViolation();
        }
    }

    public function getOrganization(): ?Organization { return $this->organization; }
    public function setOrganization(Organization $organization): static { $this->organization = $organization; return $this; }
    public function getVersionNumber(): int { return $this->versionNumber; }
    public function setVersionNumber(int $versionNumber): static { $this->versionNumber = $versionNumber; return $this; }
    public function getEffectiveFrom(): ?\DateTimeImmutable { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }
    public function getDueMode(): RentalPaymentMode { return $this->dueMode; }
    public function setDueMode(RentalPaymentMode $dueMode): static { $this->dueMode = $dueMode; return $this; }
    public function getDueDay(): int { return $this->dueDay; }
    public function setDueDay(int $dueDay): static { $this->dueDay = $dueDay; return $this; }
    public function getFirstPartialPolicy(): PartialRentPolicy { return $this->firstPartialPolicy; }
    public function setFirstPartialPolicy(PartialRentPolicy $policy): static { $this->firstPartialPolicy = $policy; return $this; }
    public function getLastPartialPolicy(): PartialRentPolicy { return $this->lastPartialPolicy; }
    public function setLastPartialPolicy(PartialRentPolicy $policy): static { $this->lastPartialPolicy = $policy; return $this; }
    public function getProrationMethod(): RentProrationMethod { return $this->prorationMethod; }
    public function setProrationMethod(RentProrationMethod $method): static { $this->prorationMethod = $method; return $this; }
    public function getRoundingRule(): RentRoundingRule { return $this->roundingRule; }
    public function setRoundingRule(RentRoundingRule $rule): static { $this->roundingRule = $rule; return $this; }
    public function getPublishedBy(): ?User { return $this->publishedBy; }
    public function setPublishedBy(User $user): static { $this->publishedBy = $user; return $this; }
    public function getPublicationReason(): string { return $this->publicationReason; }
    public function setPublicationReason(string $reason): static { $this->publicationReason = $reason; return $this; }
}
