<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CustomerVisitRequest')]
final readonly class CustomerVisitRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/')]
        #[OA\Property(type: 'string', format: 'date-time')]
        public string $scheduledAt,
    ) {
    }
}
