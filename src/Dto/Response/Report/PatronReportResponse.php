<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * PatronReportResponse
 *
 * Rapport global pour le Patron d'une Organisation.
 */
#[OA\Schema(title: 'PatronReportResponse')]
final class PatronReportResponse
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
         * @var list<FinancialSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: FinancialSummaryItem::class))]
        public array $financialSummary = [],

        /**
         * @var list<OccupancyItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OccupancyItem::class))]
        public array $occupancyByCity = [],

        /**
         * @var list<OccupancyItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OccupancyItem::class))]
        public array $occupancyByParcel = [],

        /**
         * @var list<ArrearsItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ArrearsItem::class))]
        public array $arrears = [],

        /**
         * @var list<ExpenseSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ExpenseSummaryItem::class))]
        public array $expensesByCategory = [],

        /**
         * @var list<ExpenseSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ExpenseSummaryItem::class))]
        public array $expensesByCity = [],

        #[OA\Property(description: 'Total revenus', type: 'number', format: 'decimal')]
        public string $totalRevenues = '0.00',

        #[OA\Property(description: 'Total dépenses', type: 'number', format: 'decimal')]
        public string $totalExpenses = '0.00',

        #[OA\Property(description: 'Total impayés', type: 'number', format: 'decimal')]
        public string $totalArrears = '0.00',

        #[OA\Property(description: 'Devise principale', example: 'CDF')]
        public string $currency = 'CDF',
    ) {
    }
}