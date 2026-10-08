<?php

declare(strict_types=1);

namespace App\Dto\Response\Report;

use OpenApi\Attributes as OA;

/**
 * KpiMetric
 *
 * Indicateur de performance pour le tableau de bord SUPER_ADMIN.
 */
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