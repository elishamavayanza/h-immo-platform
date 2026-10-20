<?php

declare(strict_types=1);

namespace App\Service\Rental;

use App\Entity\Identity\Organization;

/** Règles candidates immuables utilisées pour une simulation sans persistance. */
final readonly class RentalRuleCandidate
{
    public function __construct(
        public Organization $organization,
        public \DateTimeImmutable $effectiveFrom,
        public RentalRuleSet $rules,
    ) {
        if ($effectiveFrom->format('d') !== '01') {
            throw new \InvalidArgumentException('La date d’effet candidate doit être le premier jour d’un mois.');
        }
    }
}
