<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * RevenuePoint
 *
 * Point de série de revenus mensuels.
 */
#[OA\Schema(title: 'RevenuePoint')]
final class RevenuePoint
{
    public function __construct(
        #[OA\Property(description: 'Mois', example: 'Jan')]
        public string $label,

        #[OA\Property(description: 'Revenu', type: 'number', example: 21800)]
        public float $revenue,

        #[OA\Property(description: 'Abonnements', type: 'integer', example: 82)]
        public int $subscriptions,
    ) {
    }
}