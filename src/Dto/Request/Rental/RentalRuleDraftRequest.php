<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RentalRuleDraftRequest
{
    public function __construct(
        #[Assert\NotNull]
        public \DateTimeImmutable $effectiveFrom,
        #[Assert\NotNull]
        public RentalPaymentMode $dueMode,
        #[Assert\Range(min: 1, max: 31)]
        public int $dueDay,
        #[Assert\NotNull]
        public PartialRentPolicy $firstPartialPolicy,
        #[Assert\NotNull]
        public PartialRentPolicy $lastPartialPolicy,
        #[Assert\NotNull]
        public RentProrationMethod $prorationMethod,
        #[Assert\NotNull]
        public RentRoundingRule $roundingRule,
        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $changeReason,
    ) {
    }
}
