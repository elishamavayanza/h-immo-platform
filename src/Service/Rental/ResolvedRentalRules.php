<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Entity\Identity\OrganizationRentalRuleVersion;
use App\Enum\Currency;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleSource;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Symfony\Component\Uid\Uuid;

/** Résultat immuable et traçable d'une résolution des règles locatives. */
final readonly class ResolvedRentalRules
{
    public function __construct(
        public Uuid $organizationUuid,
        public Uuid $leaseUuid,
        public RentalBillingPeriod $period,
        public Currency $currency,
        public RentalPaymentMode $dueMode,
        public RentalRuleSource $dueModeSource,
        public int $dueDay,
        public RentalRuleSource $dueDaySource,
        public PartialRentPolicy $firstPartialPolicy,
        public RentalRuleSource $firstPartialPolicySource,
        public PartialRentPolicy $lastPartialPolicy,
        public RentalRuleSource $lastPartialPolicySource,
        public RentProrationMethod $prorationMethod,
        public RentalRuleSource $prorationMethodSource,
        public RentRoundingRule $roundingRule,
        public RentalRuleSource $roundingRuleSource,
        public ?int $organizationVersionId,
        public ?Uuid $organizationVersionUuid,
        public ?int $organizationVersionNumber,
        public ?\DateTimeImmutable $organizationVersionEffectiveFrom,
        public ?OrganizationRentalRuleVersion $organizationVersion,
        public ?RentalRuleCandidate $simulatedCandidate,
        public bool $simulation,
        public bool $candidateApplied,
    ) {
    }
}
