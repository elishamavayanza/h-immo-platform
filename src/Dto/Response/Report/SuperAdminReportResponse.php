<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

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

        #[OA\Property(description: 'Généré le', format: 'date-time')]
        public \DateTimeImmutable $generatedAt,

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
    ) {
    }
}