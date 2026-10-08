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

#[OA\Schema(title: 'KpiMetric')]
final class KpiMetric
{
    public function __construct(
        #[OA\Property(description: 'Identifiant unique', example: 'orgs')]
        public string $id,

        #[OA\Property(description: 'Libellé', example: 'Organisations actives')]
        public string $label,

        #[OA\Property(description: 'Valeur affichée', example: '148')]
        public string $value,

        #[OA\Property(description: 'Variation en %', type: 'number', format: 'float', example: 12.4)]
        public float $delta,

        #[OA\Property(description: 'Tendance', example: 'up')]
        public string $trend,

        #[OA\Property(description: 'Évolution favorable', example: true)]
        public bool $positive,

        #[OA\Property(description: 'Texte d\'aide', example: '12 nouvelles ce mois')]
        public string $helper,

        #[OA\Property(description: 'Tone visuelle', example: 'primary')]
        public string $tone,

        #[OA\Property(description: 'Nom de l\'icône', example: 'building')]
        public string $icon,
    ) {
    }
}

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

#[OA\Schema(title: 'ActivityEntry')]
final class ActivityEntry
{
    public function __construct(
        #[OA\Property(description: 'Identifiant', example: 'act-1')]
        public string $id,

        #[OA\Property(description: 'Acteur', example: 'Sarah Mbala')]
        public string $actor,

        #[OA\Property(description: 'Action', example: 'a créé l\'organisation')]
        public string $action,

        #[OA\Property(description: 'Cible', example: 'Kinshasa Immo Group')]
        public string $target,

        #[OA\Property(description: 'Horodatage relatif', example: 'il y a 4 min')]
        public string $timestamp,

        #[OA\Property(description: 'Type d\'activité', example: 'create')]
        public string $kind,
    ) {
    }
}

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