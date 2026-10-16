<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MaintenanceWorkOrderStatusRequest
{
    public function __construct(
        #[Assert\NotBlank, Assert\Uuid]
        public string $organizationUuid = '',
        #[Assert\NotBlank, Assert\Uuid]
        public string $cityUuid = '',
        #[Assert\Choice(choices: ['open', 'in_progress', 'completed', 'cancelled'])]
        public string $status = '',
        #[OA\Property(description: 'Date réelle d’achèvement, antérieure ou égale à aujourd’hui.', format: 'date', nullable: true, example: '2026-10-14')]
        #[Assert\Date]
        public ?string $completedAt = null,
    ) {
    }
}
