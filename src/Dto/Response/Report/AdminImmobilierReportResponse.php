<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * AdminImmobilierReportResponse
 *
 * Rapport opérationnel pour l'Administrateur Immobilier.
 */
#[OA\Schema(title: 'AdminImmobilierReportResponse')]
final class AdminImmobilierReportResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID de l\'organisation', format: 'uuid')]
        public string $organizationUuid,

        #[OA\Property(description: 'Nom de l\'organisation', example: 'ImmoCorp')]
        public string $organizationName,

        #[OA\Property(description: 'Période couverte', example: '2026-01-01 to 2026-12-31')]
        public string $periodCovered,

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,

        /**
         * @var list<OccupancyItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OccupancyItem::class))]
        public array $occupancyByParcel = [],

        /**
         * @var list<OccupancyItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OccupancyItem::class))]
        public array $occupancyByBuilding = [],

        /**
         * @var list<ArrearsItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ArrearsItem::class))]
        public array $arrears = [],

        /**
         * @var list<ExpenseSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ExpenseSummaryItem::class))]
        public array $propertyExpenses = [],

        /**
         * @var list<OccupancyItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OccupancyItem::class))]
        public array $occupancyEvolution = [],

        #[OA\Property(description: 'Total unités gérées')]
        public int $totalUnits = 0,

        #[OA\Property(description: 'Taux d\'occupation global (%)', type: 'number', format: 'float')]
        public float $globalOccupancyRate = 0.0,

        #[OA\Property(description: 'Devise principale', example: 'CDF')]
        public string $currency = 'CDF',
    ) {
    }
}