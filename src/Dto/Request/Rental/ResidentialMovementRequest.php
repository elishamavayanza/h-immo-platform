<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\ResidentialMovementStatus;
use App\Enum\ResidentialMovementType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResidentialMovementRequest
{
    public function __construct(
        #[Assert\NotNull]
        public ?ResidentialMovementType $type = null,
        #[Assert\NotNull]
        public ?ResidentialMovementStatus $status = null,
        #[Assert\Uuid]
        public ?string $leaseUuid = null,
        #[Assert\Uuid]
        public ?string $nextLeaseUuid = null,
        #[Assert\Uuid]
        public ?string $previousUnitUuid = null,
        #[Assert\Uuid]
        public ?string $nextUnitUuid = null,
        public ?\DateTimeImmutable $plannedDate = null,
        public ?\DateTimeImmutable $effectiveDate = null,
        public bool $effectiveDateEstimated = false,
        #[Assert\Length(max: 100)]
        public ?string $reason = null,
        #[Assert\Length(max: 5000)]
        public ?string $details = null,
    ) {
    }
}
