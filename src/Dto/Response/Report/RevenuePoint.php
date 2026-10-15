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

        #[OA\Property(description: 'Revenu décimal exact, en devise de référence', type: 'string', example: '21800.00')]
        public string $revenue,

        #[OA\Property(description: 'Abonnements', type: 'integer', example: 82)]
        public int $subscriptions,
    ) {
    }
}
