<?php

declare(strict_types=1);

namespace App\Dto\Request\Expense;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ExpenseFilterDto
 *
 * Package : Expense Management — DTO de requête
 *
 * Filtres pour la liste des dépenses.
 */
#[OA\Schema(
    title: 'ExpenseFilterDto',
    description: 'Critères de filtrage pour la liste des dépenses.'
)]
final readonly class ExpenseFilterDto
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

        #[OA\Property(description: 'Champ de tri', example: 'expenseDate', nullable: true)]
        public ?string $sortBy = 'expenseDate',

        #[OA\Property(description: 'Direction du tri', example: 'DESC', default: 'DESC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'DESC',
    ) {
    }
}