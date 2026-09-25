<?php

declare(strict_types=1);

namespace App\Dto\Response;

use OpenApi\Attributes as OA;

/**
 * PaginatedResponse
 *
 * Structure standard pour envelopper une collection paginée de résultats.
 */
#[OA\Schema(
    title: 'PaginatedResponse',
    description: 'Enveloppe standard pour les réponses paginées de l\'API.'
)]
final readonly class PaginatedResponse
{
    /**
     * @param array<mixed> $items Liste des éléments de la page courante (ex: DTOs de réponse)
     */
    public function __construct(
        #[OA\Property(description: 'Liste des éléments sur la page courante')]
        public array $items,

        #[OA\Property(description: 'Numéro de la page actuelle', example: 1)]
        public int $page,

        #[OA\Property(description: 'Nombre d\'éléments par page', example: 10)]
        public int $limit,

        #[OA\Property(description: 'Nombre total d\'éléments', example: 45)]
        public int $totalItems,

        #[OA\Property(description: 'Nombre total de pages', example: 5)]
        public int $totalPages,
    ) {
    }

    /**
     * Factory de construction rapide à partir des valeurs.
     */
    public static function create(array $items, int $page, int $limit, int $totalItems): self
    {
        $totalPages = (int) ceil($totalItems / ($limit > 0 ? $limit : 1));

        return new self(
            items: $items,
            page: $page,
            limit: $limit,
            totalItems: $totalItems,
            totalPages: $totalPages,
        );
    }
}
