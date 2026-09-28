<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\Currency;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * LeaseRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un contrat de bail (Lease).
 *
 * Le statut, la date de résiliation et son motif en sont absents, et c'est
 * délibéré : un bail naît DRAFT et change d'état par des transitions
 * explicites (activer, résilier, annuler), chacune soumise à son propre
 * contrôle d'accès. Les exposer en écriture permettait de repasser un bail
 * ACTIVE en DRAFT en ne changeant que sa date de fin.
 */
#[OA\Schema(
    title: 'LeaseRequest',
    description: 'Payload pour la création ou la modification d\'un contrat de location.'
)]
final readonly class LeaseRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public du locataire rattaché',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $tenantUuid = null,

        #[OA\Property(
            description: 'UUID public du local / unité locative concernée',
            format: 'uuid',
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $unitUuid = null,

        #[OA\Property(
            description: 'Référence unique du contrat de bail',
            example: 'LEASE-2026-0042',
            maxLength: 50
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Date de prise d\'effet du bail',
            format: 'date',
            example: '2026-01-01'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?\DateTimeImmutable $startDate = null,

        #[OA\Property(
            description: 'Date d\'échéance du bail (null si bail à durée indéterminée)',
            format: 'date',
            example: '2026-12-31',
            nullable: true
        )]
        public ?\DateTimeImmutable $endDate = null,

        #[OA\Property(
            description: 'Montant du loyer mensuel convenu',
            example: '500.00'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $monthlyRent = null,

        #[OA\Property(
            description: 'Montant de la garantie locative / caution',
            example: '1000.00',
            nullable: true
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?string $depositAmount = null,

        #[OA\Property(
            description: 'Devise pour les transactions liées au contrat',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Notes ou clauses particulières',
            example: 'Clause de révision annuelle de 5% incluse.',
            nullable: true
        )]
        public ?string $notes = null,
    ) {
    }
}
