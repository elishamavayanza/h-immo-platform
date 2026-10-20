<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Paramètres candidats facultatifs pour comparer un brouillon aux règles publiées. */
final readonly class RentalRuleSimulationCandidateRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?\DateTimeImmutable $effectiveFrom = null,
        #[Assert\NotNull]
        public ?RentalPaymentMode $dueMode = null,
        #[Assert\Range(min: 1, max: 31)]
        public ?int $dueDay = null,
        #[Assert\NotNull]
        public ?PartialRentPolicy $firstPartialPolicy = null,
        #[Assert\NotNull]
        public ?PartialRentPolicy $lastPartialPolicy = null,
        #[Assert\NotNull]
        public ?RentProrationMethod $prorationMethod = null,
        #[Assert\NotNull]
        public ?RentRoundingRule $roundingRule = null,
    ) {
    }
}
