<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Pagination de la liste des biens éligibles à une annonce. */
#[OA\Schema(title: 'ListingCandidateFilterRequest')]
final readonly class ListingCandidateFilterRequest
{
    public function __construct(
        #[Assert\Positive]
        #[OA\Property(type: 'integer', minimum: 1, default: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        #[OA\Property(type: 'integer', minimum: 1, maximum: 100, default: 100)]
        public int $limit = 100,
        #[Assert\Uuid]
        #[OA\Property(type: 'string', format: 'uuid', nullable: true)]
        public ?string $cityUuid = null,
    ) {
    }
}
