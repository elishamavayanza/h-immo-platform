<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * SuperAdminDashboardResponse
 *
 * Tableau de bord temps réel pour SUPER_ADMIN (plateforme entière).
 * Indicateurs globaux des comptes et événements d'administration plateforme.
 */
#[OA\Schema(title: 'SuperAdminDashboardResponse')]
final class SuperAdminDashboardResponse
{
    public function __construct(
        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,

        #[OA\Property(description: 'Nombre total d’organisations non supprimées')]
        public int $totalOrganizations = 0,

        #[OA\Property(description: 'Organisations actives')]
        public int $activeOrganizations = 0,

        #[OA\Property(description: 'Organisations suspendues')]
        public int $suspendedOrganizations = 0,

        #[OA\Property(description: 'Organisations inactives')]
        public int $inactiveOrganizations = 0,

        #[OA\Property(description: 'Nombre total de comptes non supprimés')]
        public int $totalUsers = 0,

        #[OA\Property(description: 'Comptes actifs')]
        public int $activeUsers = 0,

        #[OA\Property(description: 'Comptes désactivés')]
        public int $disabledUsers = 0,

        /** @var list<PlatformActivityEntry> */
        #[OA\Property(type: 'array', items: new OA\Items(ref: PlatformActivityEntry::class))]
        public array $activity = [],
    ) {
    }
}
