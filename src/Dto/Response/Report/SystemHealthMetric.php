<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * SystemHealthMetric
 *
 * Métrique de santé système.
 */
#[OA\Schema(title: 'SystemHealthMetric')]
final class SystemHealthMetric
{
    public function __construct(
        #[OA\Property(description: 'Identifiant', example: 'cpu')]
        public string $id,

        #[OA\Property(description: 'Libellé', example: 'CPU')]
        public string $label,

        #[OA\Property(description: 'Valeur', type: 'number', example: 38)]
        public float $value,

        #[OA\Property(description: 'Unité', example: '%')]
        public string $unit,

        #[OA\Property(description: 'Statut', example: 'healthy')]
        public string $status,
    ) {
    }
}