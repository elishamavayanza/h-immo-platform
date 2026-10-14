<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/** Bail actif dont la fin contractuelle approche. */
#[OA\Schema(title: 'LeaseExpiryItem')]
final class LeaseExpiryItem
{
    public function __construct(
        #[OA\Property(description: 'UUID du bail', format: 'uuid')]
        public string $leaseUuid,
        #[OA\Property(description: 'Référence du bail')]
        public string $leaseReference,
        #[OA\Property(description: 'Nom du locataire')]
        public string $tenantName,
        #[OA\Property(description: 'Référence de l’unité')]
        public string $unitLabel,
        #[OA\Property(description: 'Ville du bien')]
        public string $cityName,
        #[OA\Property(description: 'Date de fin prévue', format: 'date')]
        public string $endDate,
        #[OA\Property(description: 'Nombre de jours avant la fin')]
        public int $daysRemaining,
    ) {
    }
}
