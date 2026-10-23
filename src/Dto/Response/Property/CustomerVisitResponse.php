<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'CustomerVisitResponse')]
final readonly class CustomerVisitResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')] public string $uuid,
        #[OA\Property(format: 'uuid')] public string $inquiryUuid,
        public string $scheduledAt,
        public string $status,
        #[OA\Property(nullable: true)] public ?string $outcome,
        public string $createdBy,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
