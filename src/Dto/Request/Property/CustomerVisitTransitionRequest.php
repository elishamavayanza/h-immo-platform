<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(title: 'CustomerVisitTransitionRequest')]
final readonly class CustomerVisitTransitionRequest
{
    public function __construct(
        #[Assert\Choice(choices: ['confirmed', 'completed', 'cancelled'])]
        #[OA\Property(type: 'string', enum: ['confirmed', 'completed', 'cancelled'])]
        public string $status,
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', maxLength: 1000, nullable: true)]
        public ?string $reason = null,
    ) {
    }
}
