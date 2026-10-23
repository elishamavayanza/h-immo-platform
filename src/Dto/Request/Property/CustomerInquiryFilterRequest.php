<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CustomerInquiryFilterRequest')]
final readonly class CustomerInquiryFilterRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed'])]
        #[OA\Property(type: 'string', nullable: true)]
        public ?string $status = null,
        #[Assert\Choice(choices: ['information', 'visit'])]
        #[OA\Property(type: 'string', enum: ['information', 'visit'], nullable: true)]
        public ?string $type = null,
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,
    ) {
    }
}
