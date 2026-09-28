<?php

declare(strict_types=1);

namespace App\Dto\Request\Staff;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WorkerFilterDto
 *
 * Package : Staff Management — DTO de requête
 *
 * Filtres pour la liste des travailleurs.
 */
#[OA\Schema(
    title: 'WorkerFilterDto',
    description: 'Critères de filtrage pour la liste des travailleurs.'
)]
final readonly class WorkerFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par organisation (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $organizationId = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page', default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,
    ) {
    }
}