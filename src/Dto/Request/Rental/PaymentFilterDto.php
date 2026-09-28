<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PaymentFilterDto
 *
 * Package : Rental Management — DTO de requête
 *
 * Filtres pour la liste des paiements.
 */
#[OA\Schema(
    title: 'PaymentFilterDto',
    description: 'Critères de filtrage pour la liste des paiements.'
)]
final readonly class PaymentFilterDto
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

        #[OA\Property(description: 'Champ de tri', example: 'paymentDate', nullable: true)]
        public ?string $sortBy = 'paymentDate',

        #[OA\Property(description: 'Direction du tri', example: 'DESC', default: 'DESC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'DESC',
    ) {
    }
}