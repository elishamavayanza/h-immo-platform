<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MaintenanceWorkOrderFilter
{
    public function __construct(
        #[Assert\NotBlank, Assert\Uuid]
        public string $organizationUuid = '',
        #[Assert\NotBlank, Assert\Uuid]
        public string $cityUuid = '',
        #[Assert\Choice(choices: ['open', 'in_progress', 'completed', 'cancelled'])]
        public ?string $status = null,
    ) {
    }
}
