<?php

declare(strict_types=1);

namespace App\Dto\Request\Financial;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CityExchangeRatePermissionRequest
{
    public function __construct(
        #[Assert\NotNull]
        #[Assert\Uuid]
        public ?string $organizationUserUuid = null,
        public bool $allowed = false,
    ) {
    }
}
