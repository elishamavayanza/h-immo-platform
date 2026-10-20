<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RentalRuleDelegationRevokeRequest
{
    public function __construct(
        #[Assert\Length(max: 500)]
        public ?string $reason = null,
    ) {
    }
}
