<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class MaintenanceWorkOrderRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Uuid]
        public string $organizationUuid = '',
        #[Assert\NotBlank, Assert\Uuid]
        public string $cityUuid = '',
        #[Assert\NotBlank, Assert\Length(max: 180)]
        public string $title = '',
        #[Assert\NotBlank, Assert\Length(max: 5000)]
        public string $description = '',
        #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
        public string $priority = 'normal',
        #[Assert\Uuid]
        public ?string $parcelUuid = null,
        #[Assert\Uuid]
        public ?string $buildingUuid = null,
        #[Assert\Uuid]
        public ?string $unitUuid = null,
        #[Assert\Uuid]
        public ?string $workerAssignmentUuid = null,
        #[Assert\Date]
        public ?string $dueDate = null,
        #[Assert\Date]
        public ?string $reportedAt = null,
    ) {
    }
}
