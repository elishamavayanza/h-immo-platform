<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;

/** Valeurs immuables d'un ensemble cohérent de règles, sans provenance implicite. */
final readonly class RentalRuleSet
{
    public function __construct(
        public RentalPaymentMode $dueMode,
        public int $dueDay,
        public PartialRentPolicy $firstPartialPolicy,
        public PartialRentPolicy $lastPartialPolicy,
        public RentProrationMethod $prorationMethod,
        public RentRoundingRule $roundingRule,
    ) {
        if ($dueDay < 1 || $dueDay > 31) {
            throw new \InvalidArgumentException('Le jour d’exigibilité doit être compris entre 1 et 31.');
        }
    }
}
