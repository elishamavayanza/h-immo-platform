<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

#[OA\Schema(title: 'PlatformActivityEntry')]
final class PlatformActivityEntry
{
    public function __construct(
        #[OA\Property(description: 'Identifiant de l’événement')]
        public string $id,

        #[OA\Property(description: 'Événement d’administration, sans détail métier d’organisation')]
        public string $action,

        #[OA\Property(description: 'Horodatage relatif', example: 'il y a 4 min')]
        public string $timestamp,

        #[OA\Property(description: 'Catégorie de l’événement', enum: ['create', 'update', 'alert'])]
        public string $kind,
    ) {
    }
}
