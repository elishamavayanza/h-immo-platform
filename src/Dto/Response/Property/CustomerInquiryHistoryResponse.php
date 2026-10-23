<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'CustomerInquiryHistoryResponse')]
final readonly class CustomerInquiryHistoryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')] public string $uuid,
        public string $action,
        #[OA\Property(nullable: true)] public ?string $oldStatus,
        public string $newStatus,
        #[OA\Property(nullable: true)] public ?string $reason,
        #[OA\Property(nullable: true)] public ?string $actor,
        public string $createdAt,
    ) {
    }
}
