<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

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