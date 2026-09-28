<?php

declare(strict_types=1);

namespace App\Dto\Request\Expense;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ExpenseCancelRequest
 *
 * Package : Expense Management — DTO de requête
 *
 * Justification pour l'annulation d'une dépense (contre-écriture).
 */
#[OA\Schema(
    title: 'ExpenseCancelRequest',
    description: 'Motif d\'annulation d\'une dépense.'
)]
final readonly class ExpenseCancelRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Motif de l\'annulation, conservé comme trace dans l\'audit',
            example: 'Erreur de saisie : montant incorrect',
            maxLength: 255
        )]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $reason = null,
    ) {
    }
}