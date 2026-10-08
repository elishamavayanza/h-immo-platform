<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * ActivityEntry
 *
 * Entrée du flux d'activité (depuis les logs d'audit).
 */
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