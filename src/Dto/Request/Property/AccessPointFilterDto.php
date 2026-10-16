<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AccessPointFilterDto
{
    public function __construct(
        #[Assert\Uuid] public ?string $buildingUuid = null,
        #[Assert\Uuid] public ?string $parcelUuid = null,
    ) {}
}
