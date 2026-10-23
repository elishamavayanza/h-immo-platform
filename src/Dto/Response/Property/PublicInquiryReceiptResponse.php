<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'PublicInquiryReceiptResponse')]
final readonly class PublicInquiryReceiptResponse
{
    public function __construct(
        #[OA\Property(format: 'uuid')]
        public string $reference,
        #[OA\Property(format: 'date-time')]
        public string $receivedAt,
    ) {
    }
}
