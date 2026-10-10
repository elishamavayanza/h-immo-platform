<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UnitFilterDto
 *
 * Package : Property Management — DTO de requête
 *
 * Filtres de la liste des unités. Le drill-down patrimonial passe par
 * `buildingUuid` (bâtiments) ; `organizationId` resserre la liste à une
 * organisation du périmètre de l'appelant. Hors périmètre : liste vide.
 */
#[OA\Schema(
    title: 'UnitFilterDto',
    description: 'Critères de filtrage pour la liste des unités locatives.'
)]
final readonly class UnitFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par organisation (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $organizationId = null,

        #[OA\Property(description: 'Filtrer par bâtiment parent (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $buildingUuid = null,

        #[OA\Property(description: 'Terme de recherche (référence, libellé)', nullable: true)]
        public ?string $search = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page (max 100)', default: 10)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[OA\Property(description: 'Champ de tri', example: 'reference', nullable: true)]
        public ?string $sortBy = 'reference',

        #[OA\Property(description: 'Direction du tri', example: 'ASC', default: 'ASC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'ASC',
    ) {
    }
}