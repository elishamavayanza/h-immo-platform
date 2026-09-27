<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * FinancialSummaryItem
 *
 * Résumé financier par période (mois/année).
 */
#[OA\Schema(title: 'FinancialSummaryItem')]
final class FinancialSummaryItem
{
    public function __construct(
        #[OA\Property(description: 'Période (YYYY-MM)', example: '2026-01')]
        public string $period,

        #[OA\Property(description: 'Revenus (loyers encaissés)', type: 'number', format: 'decimal')]
        public string $revenues,

        #[OA\Property(description: 'Dépenses', type: 'number', format: 'decimal')]
        public string $expenses,

        #[OA\Property(description: 'Résultat net', type: 'number', format: 'decimal')]
        public string $netResult,

        #[OA\Property(description: 'Devise')]
        public string $currency,
    ) {
    }
}

/**
 * OccupancyItem
 *
 * Taux d'occupation par ville/parcelle/immeuble.
 */
#[OA\Schema(title: 'OccupancyItem')]
final class OccupancyItem
{
    public function __construct(
        #[OA\Property(description: 'Niveau : city|parcel|building', example: 'city')]
        public string $level,

        #[OA\Property(description: 'UUID du niveau', format: 'uuid', nullable: true)]
        public ?string $levelUuid = null,

        #[OA\Property(description: 'Nom/Libellé', example: 'Kinshasa')]
        public string $label,

        #[OA\Property(description: 'Total unités')]
        public int $totalUnits,

        #[OA\Property(description: 'Unités occupées')]
        public int $occupiedUnits,

        #[OA\Property(description: 'Unités disponibles')]
        public int $availableUnits,

        #[OA\Property(description: 'Taux d\'occupation (%)', type: 'number', format: 'float')]
        public float $occupancyRate,
    ) {
    }
}

/**
 * ArrearsItem
 *
 * Impayé / retard par locataire/contrat.
 */
#[OA\Schema(title: 'ArrearsItem')]
final class ArrearsItem
{
    public function __construct(
        #[OA\Property(description: 'UUID du bail', format: 'uuid')]
        public string $leaseUuid,

        #[OA\Property(description: 'Référence du bail', example: 'BAIL-2026-001')]
        public string $leaseReference,

        #[OA\Property(description: 'Nom du locataire', example: 'Jean Dupont')]
        public string $tenantName,

        #[OA\Property(description: 'Unité concernée', example: 'A-101')]
        public string $unitLabel,

        #[OA\Property(description: 'Montant dû', type: 'number', format: 'decimal')]
        public string $amountDue,

        #[OA\Property(description: 'Montant payé', type: 'number', format: 'decimal')]
        public string $amountPaid,

        #[OA\Property(description: 'Impayé', type: 'number', format: 'decimal')]
        public string $arrears,

        #[OA\Property(description: 'Jours de retard')]
        public int $daysOverdue,

        #[OA\Property(description: 'Devise')]
        public string $currency,
    ) {
    }
}

/**
 * ExpenseSummaryItem
 *
 * Résumé des dépenses par catégorie/niveau.
 */
#[OA\Schema(title: 'ExpenseSummaryItem')]
final class ExpenseSummaryItem
{
    public function __construct(
        #[OA\Property(description: 'Catégorie', example: 'MAINTENANCE')]
        public string $category,

        #[OA\Property(description: 'Niveau : city|parcel|building|unit|organization', example: 'building')]
        public string $level,

        #[OA\Property(description: 'UUID du niveau', format: 'uuid', nullable: true)]
        public ?string $levelUuid = null,

        #[OA\Property(description: 'Libellé du niveau', example: 'Immeuble Central')]
        public string $levelLabel,

        #[OA\Property(description: 'Nombre de dépenses')]
        public int $count,

        #[OA\Property(description: 'Montant total', type: 'number', format: 'decimal')]
        public string $totalAmount,

        #[OA\Property(description: 'Devise')]
        public string $currency,
    ) {
    }
}

/**
 * WorkerActivityItem
 *
 * Activité des travailleurs pour ADMIN_VILLE.
 */
#[OA\Schema(title: 'WorkerActivityItem')]
final class WorkerActivityItem
{
    public function __construct(
        #[OA\Property(description: 'UUID du travailleur', format: 'uuid')]
        public string $workerUuid,

        #[OA\Property(description: 'Nom complet', example: 'Jean Dupont')]
        public string $fullName,

        #[OA\Property(description: 'Rôle', example: 'GERANT')]
        public string $role,

        #[OA\Property(description: 'Immeuble/Unité affectée', example: 'Immeuble Central / A-101')]
        public string $assignmentLabel,

        #[OA\Property(description: 'Salaire mensuel', type: 'number', format: 'decimal')]
        public string $monthlySalary,

        #[OA\Property(description: 'Devise')]
        public string $currency,
    ) {
    }
}