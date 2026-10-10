<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * RentFilterDto
 *
 * Package : Rental Management — DTO de requête
 *
 * Filtres pour la liste générale des échéances. `organisationId` et
 * `leaseUuid` resserrent la requête à un périmètre vérifié : toute valeur
 * hors périmètre renvoie une liste vide (pas d'énumération). `status`
 * compare au statut CALCULÉ (computed) exposé par `RentResponse`, jamais
 * au statut persisté : `overdue` est un état dérivé à la lecture.
 */
#[OA\Schema(
    title: 'RentFilterDto',
    description: 'Critères de filtrage pour la liste des échéances de loyer.'
)]
final readonly class RentFilterDto
{
    public function __construct(
        #[OA\Property(description: 'Filtrer par organisation (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $organizationId = null,

        #[OA\Property(description: 'Filtrer par bail (UUID)', format: 'uuid', nullable: true)]
        #[Assert\Uuid]
        public ?string $leaseUuid = null,

        #[OA\Property(
            description: 'Statut calculé (pending, partially_paid, paid, overdue)',
            enum: ['pending', 'partially_paid', 'paid', 'overdue'],
            nullable: true
        )]
        #[Assert\Choice(choices: ['pending', 'partially_paid', 'paid', 'overdue'])]
        #[Assert\Choice(choices: ['pending', 'partially_paid', 'paid', 'overdue'])]
        public ?string $status = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        #[Assert\Positive]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page (max 100)', default: 20)]
        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,

        #[OA\Property(description: 'Champ de tri', example: 'dueDate', nullable: true)]
        public ?string $sortBy = 'dueDate',

        #[OA\Property(description: 'Direction du tri', example: 'ASC', default: 'ASC')]
        #[Assert\Choice(choices: ['ASC', 'DESC', 'asc', 'desc'])]
        public string $sortOrder = 'ASC',
    ) {
    }
}