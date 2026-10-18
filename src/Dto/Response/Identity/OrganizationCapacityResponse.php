<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use OpenApi\Attributes as OA;

/** Capacités effectives et consommation privée d’une organisation. */
#[OA\Schema(title: 'OrganizationCapacityResponse')]
final readonly class OrganizationCapacityResponse
{
    /**
     * @param array{cities:?int,parcels:?int,buildings:?int,units:?int,members:?int} $limits
     * @param array{cities:int,parcels:int,buildings:int,units:int,members:int} $usage
     */
    public function __construct(
        #[OA\Property(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer', nullable: true))]
        public array $limits,
        #[OA\Property(type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'integer'))]
        public array $usage,
        #[OA\Property(description: 'La vitrine est-elle autorisée par la plateforme ?', example: false)]
        public bool $showcaseEnabled,
    ) {
    }
}
