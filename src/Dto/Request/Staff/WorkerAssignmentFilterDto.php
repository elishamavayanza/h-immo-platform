<?php

declare(strict_types=1);

namespace App\Dto\Request\Staff;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WorkerAssignmentFilterDto
 *
 * Package : Staff Management — DTO de requête
 *
 * Filtres pour la liste des affectations.
 */
#[OA\Schema(
    title: 'WorkerAssignmentFilterDto',
    description: 'Critères de filtrage pour la liste des affectations.'
)]
final readonly class WorkerAssignmentFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par villes (UUIDs)', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))]
        public ?array $cityIds = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page', default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,

        #[OA\Property(description: 'Champ de tri', example: 'startDate', nullable: true)]
        public ?string $sortBy = 'startDate',

        #[OA\Property(description: 'Direction du tri', example: 'DESC', default: 'DESC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'DESC',
    ) {
    }
}