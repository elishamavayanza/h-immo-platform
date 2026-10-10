<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CityFilterDto
 *
 * Package : Property Management — DTO de requête
 *
 * Filtres de la liste des villes. `organizationId` resserre la liste à une
 * organisation du périmètre de l'appelant ; hors périmètre, liste vide.
 */
#[OA\Schema(
    title: 'CityFilterDto',
    description: 'Critères de filtrage pour la liste des villes d\'exploitation.'
)]
final readonly class CityFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par organisation (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $organizationId = null,

        #[OA\Property(description: 'Terme de recherche (nom, code)', nullable: true)]
        public ?string $search = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page (max 100)', default: 10)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 10,

        #[OA\Property(description: 'Champ de tri', example: 'name', nullable: true)]
        public ?string $sortBy = 'name',

        #[OA\Property(description: 'Direction du tri', example: 'ASC', default: 'ASC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'ASC',
    ) {
    }
}