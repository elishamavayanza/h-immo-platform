<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LeaseOccupantDepartureRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $effectiveDate,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $reason,
    ) {
    }
}
