<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\Currency;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * RentRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une échéance (Rent).
 *
 * Le statut n'y figure pas, et c'est délibéré : il se déduit des
 * paiements reçus et de la date d'exigibilité (`Rent::syncStatus()`).
 * L'exposer en écriture permettait deux corruptions silencieuses — remettre
 * une échéance soldée à « pending » en ne changeant que sa date, et
 * déclarer « payé » sans enregistrer le moindre paiement.
 */
#[OA\Schema(
    title: 'RentRequest',
    description: 'Payload pour la génération ou la régularisation manuelle d\'une échéance de loyer.'
)]
final readonly class RentRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public du contrat de bail associé',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $leaseUuid = null,

        #[OA\Property(
            description: 'Mois/Période couverte par le loyer (généralement le 1er du mois)',
            format: 'date',
            example: '2026-09-01'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?\DateTimeImmutable $period = null,

        #[OA\Property(
            description: 'Date limite d\'exigibilité du règlement',
            format: 'date',
            example: '2026-09-05'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?\DateTimeImmutable $dueDate = null,

        #[OA\Property(
            description: 'Montant facturé pour la période',
            example: '500.00'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $amount = null,

        #[OA\Property(
            description: 'Devise de facturation',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?Currency $currency = null,
    ) {
    }
}
