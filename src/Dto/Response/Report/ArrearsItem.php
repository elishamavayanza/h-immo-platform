<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

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