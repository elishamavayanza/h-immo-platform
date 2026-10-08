<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * CityShare
 *
 * Part géographique des propriétés par ville.
 */
#[OA\Schema(title: 'CityShare')]
final class CityShare
{
    public function __construct(
        #[OA\Property(description: 'Nom de la ville', example: 'Kinshasa')]
        public string $name,

        #[OA\Property(description: 'Nombre de propriétés', type: 'integer', example: 62)]
        public int $count,

        #[OA\Property(description: 'Part relative', type: 'number', format: 'float', example: 0.42)]
        public float $share,
    ) {
    }
}