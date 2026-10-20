<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RentalRuleDelegationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $userUuid,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {
    }
}
