<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CustomerInquiryTransitionRequest')]
final readonly class CustomerInquiryTransitionRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['contacted', 'negotiation', 'closed'])]
        #[OA\Property(type: 'string', enum: ['contacted', 'negotiation', 'closed'])]
        public string $status,
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', maxLength: 1000, nullable: true)]
        public ?string $reason = null,
    ) {
    }
}
