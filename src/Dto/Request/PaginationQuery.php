<?php

declare(strict_types=1);

namespace App\Dto\Request;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PaginationQuery
 *
 * DTO capturant les paramètres de requête HTTP pour la pagination et la recherche.
 */
final readonly class PaginationQuery
{
    public function __construct(
        #[Assert\Positive]
        #[OA\Property(description: 'Numéro de la page demandée', example: 1, default: 1)]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        #[OA\Property(description: 'Nombre de résultats par page (max 100)', example: 10, default: 10)]
        public int $limit = 10,

        #[OA\Property(description: 'Terme de recherche global', example: 'Maniema', nullable: true)]
        public ?string $search = null,

        #[OA\Property(description: 'Champ de tri', example: 'createdAt', nullable: true)]
        public ?string $sortBy = 'createdAt',

        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        #[OA\Property(description: 'Direction du tri (ASC ou DESC)', example: 'DESC', default: 'DESC')]
        public string $sortOrder = 'DESC',
    ) {
    }
}
