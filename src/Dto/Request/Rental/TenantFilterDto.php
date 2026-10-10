<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TenantFilterDto
 *
 * Package : Rental Management — DTO de requête
 *
 * Filtres pour la liste des locataires. `organizationId` resserre la liste
 * à une organisation du périmètre de l'appelant : une organisation hors
 * périmètre renvoie une liste vide plutôt qu'une erreur (pas d'énumération).
 */
#[OA\Schema(
    title: 'TenantFilterDto',
    description: 'Critères de filtrage pour la liste des locataires.'
)]
final readonly class TenantFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par organisation (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $organizationId = null,

        #[OA\Property(description: 'Terme de recherche (nom, entreprise, téléphone, email)', nullable: true)]
        public ?string $search = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page (max 100)', default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,

        #[OA\Property(description: 'Champ de tri', example: 'fullName', nullable: true)]
        public ?string $sortBy = 'fullName',

        #[OA\Property(description: 'Direction du tri', example: 'ASC', default: 'ASC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'ASC',
    ) {
    }
}