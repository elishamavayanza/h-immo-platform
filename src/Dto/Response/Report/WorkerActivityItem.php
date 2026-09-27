<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

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