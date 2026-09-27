<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * OccupancyItem
 *
 * Taux d'occupation par ville/parcelle/immeuble.
 */
#[OA\Schema(title: 'OccupancyItem')]
final class OccupancyItem
{
    public function __construct(
        #[OA\Property(description: 'Niveau : city|parcel|building', example: 'city')]
        public string $level,

        #[OA\Property(description: 'UUID du niveau', format: 'uuid', nullable: true)]
        public ?string $levelUuid = null,

        #[OA\Property(description: 'Nom/Libellé', example: 'Kinshasa')]
        public string $label,

        #[OA\Property(description: 'Total unités')]
        public int $totalUnits,

        #[OA\Property(description: 'Unités occupées')]
        public int $occupiedUnits,

        #[OA\Property(description: 'Unités disponibles')]
        public int $availableUnits,

        #[OA\Property(description: 'Taux d\'occupation (%)', type: 'number', format: 'float')]
        public float $occupancyRate,
    ) {
    }
}