<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * SuperAdminDashboardResponse
 *
 * Tableau de bord temps réel pour SUPER_ADMIN (plateforme entière).
 * Données agrégées pour la vue d'ensemble : KPIs, séries, activité, santé.
 */
#[OA\Schema(title: 'SuperAdminDashboardResponse')]
final class SuperAdminDashboardResponse
{
    public function __construct(
        #[OA\Property(description: 'Période couverte', example: '2026-01-01 to 2026-12-31')]
        public string $periodCovered,

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,

        /**
         * @var list<KpiMetric>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: KpiMetric::class))]
        public array $kpis = [],

        /**
         * @var list<RevenuePoint>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: RevenuePoint::class))]
        public array $revenueSeries = [],

        /**
         * @var list<OrganizationSummaryItem>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: OrganizationSummaryItem::class))]
        public array $recentOrganizations = [],

        /**
         * @var list<ActivityEntry>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: ActivityEntry::class))]
        public array $activity = [],

        /**
         * @var list<SystemHealthMetric>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: SystemHealthMetric::class))]
        public array $health = [],

        /**
         * @var list<CityShare>
         */
        #[OA\Property(type: 'array', items: new OA\Items(ref: CityShare::class))]
        public array $topCities = [],

        // Totaux globaux
        #[OA\Property(description: 'Total organisations')]
        public int $totalOrganizations = 0,

        #[OA\Property(description: 'Organisations actives')]
        public int $activeOrganizations = 0,

        #[OA\Property(description: 'Total utilisateurs plateforme')]
        public int $totalUsers = 0,
    ) {
    }
}