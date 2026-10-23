<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'CustomerInquiryResponse')]
final readonly class CustomerInquiryResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')] public string $uuid,
        #[OA\Property(format: 'uuid')] public string $listingId,
        public string $listingTitle,
        public string $listingReference,
        public string $city,
        public string $building,
        public string $unitType,
        public string $surface,
        public ?int $bedrooms,
        public ?int $rooms,
        public ?int $bathrooms,
        public string $offerType,
        public string $price,
        public string $currency,
        public string $type,
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $message,
        public string $status,
        #[OA\Property(nullable: true)] public ?string $assignedTo,
        public string $createdAt,
        public string $updatedAt,
        #[OA\Property(nullable: true)] public ?string $closedReason,
        #[OA\Property(format: 'uuid', nullable: true)] public ?string $convertedLeaseUuid,
        #[OA\Property(nullable: true)] public ?string $convertedLeaseReference,
    ) {
    }
}
