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

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,
    ) {
    }
}

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

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,
    ) {
    }
}

/**
 * AdminVilleReportResponse
 *
 * Rapport pour l'Administrateur de Ville (scope limité à ses villes).
 */
#[OA\Schema(title: 'AdminVilleReportResponse')]
final class AdminVilleReportResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID de la ville', format: 'uuid')]
        public string $cityUuid,

        #[OA\Property(description: 'Nom de la ville', example: 'Kinshasa')]
        public string $cityName,

        #[OA\Property(description: 'Période couverte', example: '2026-01-01 to 2026-12-31')]
        public string $periodCovered,

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
        public array $cityExpenses = [],

        /**
         * @var list<WorkerActivityItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: WorkerActivityItem::class))]
        public array $workers = [],

        #[OA\Property(description: 'Total unités dans la ville')]
        public int $totalUnits = 0,

        #[OA\Property(description: 'Taux d\'occupation ville (%)', type: 'number', format: 'float')]
        public float $occupancyRate = 0.0,

        #[OA\Property(description: 'Total dépenses ville', type: 'number', format: 'decimal')]
        public string $totalExpenses = '0.00',

        #[OA\Property(description: 'Devise principale', example: 'CDF')]
        public string $currency = 'CDF',

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,
    ) {
    }
}

/**
 * SuperAdminReportResponse
 *
 * Rapport global pour SUPER_ADMIN (plateforme entière).
 */
#[OA\Schema(title: 'SuperAdminReportResponse')]
final class SuperAdminReportResponse
{
    public function __construct(
        #[OA\Property(description: 'Période couverte', example: '2026-01-01 to 2026-12-31')]
        public string $periodCovered,

        /**
         * @var list<OrganizationSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OrganizationSummaryItem::class))]
        public array $organizations = [],

        #[OA\Property(description: 'Total organisations')]
        public int $totalOrganizations = 0,

        #[OA\Property(description: 'Organisations actives')]
        public int $activeOrganizations = 0,

        #[OA\Property(description: 'Total utilisateurs plateforme')]
        public int $totalUsers = 0,

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,
    ) {
    }
}

/**
 * OrganizationSummaryItem
 *
 * Résumé par organisation pour SUPER_ADMIN.
 */
#[OA\Schema(title: 'OrganizationSummaryItem')]
final class OrganizationSummaryItem
{
    public function __construct(
        #[OA\Property(description: 'UUID', format: 'uuid')]
        public string $uuid,

        #[OA\Property(description: 'Nom', example: 'ImmoCorp')]
        public string $name,

        #[OA\Property(description: 'Code', example: 'IMC')]
        public string $code,

        #[OA\Property(description: 'Statut', example: 'ACTIVE')]
        public string $status,

        #[OA\Property(description: 'Nombre de villes')]
        public int $cityCount,

        #[OA\Property(description: 'Nombre d\'unités')]
        public int $unitCount,

        #[OA\Property(description: 'Taux d\'occupation global', type: 'number', format: 'float')]
        public float $occupancyRate,

        #[OA\Property(description: 'Revenus période', type: 'number', format: 'decimal')]
        public string $revenues,

        #[OA\Property(description: 'Dépenses période', type: 'number', format: 'decimal')]
        public string $expenses,

        #[OA\Property(description: 'Impayés', type: 'number', format: 'decimal')]
        public string $arrears,
    ) {
    }
}