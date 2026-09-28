<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PaymentCancelRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Motif d'annulation d'un paiement (contre-écriture).
 */
#[OA\Schema(
    title: 'PaymentCancelRequest',
    description: 'Motif d\'annulation d\'un paiement.'
)]
final readonly class PaymentCancelRequest
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