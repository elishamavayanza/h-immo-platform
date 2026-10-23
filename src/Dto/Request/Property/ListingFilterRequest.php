<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/** Filtres paginés de la file éditoriale d'une organisation. */
#[OA\Schema(title: 'ListingFilterRequest')]
final readonly class ListingFilterRequest
{
    public function __construct(
        #[Assert\Positive]
        #[OA\Property(type: 'integer', minimum: 1, default: 1)]
        public int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        #[OA\Property(type: 'integer', minimum: 1, maximum: 100, default: 20)]
        public int $limit = 20,
        #[Assert\Choice(choices: ['draft', 'pending_review', 'corrections_requested', 'pending_publication', 'published', 'rejected', 'withdrawn'])]
        #[OA\Property(type: 'string', nullable: true, enum: ['draft', 'pending_review', 'corrections_requested', 'pending_publication', 'published', 'rejected', 'withdrawn'])]
        public ?string $status = null,
        #[Assert\Uuid]
        #[OA\Property(type: 'string', format: 'uuid', nullable: true)]
        public ?string $cityUuid = null,
    ) {
    }
}
