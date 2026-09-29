<?php

declare(strict_types=1);

namespace App\Dto\Response\Rental;

use App\Entity\Rental\Lease;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use OpenApi\Attributes as OA;

/**
 * LeaseResponse
 *
 * Package : Rental Management — DTO de réponse
 */
#[OA\Schema(
    title: 'LeaseResponse',
    description: 'Représentation publique d\'un contrat de bail.'
)]
final readonly class LeaseResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public du contrat de bail', format: 'uuid', example: 'e3b0c442-98fc-4282-9a3b-2b0d7b3dcb6d')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation rattachée', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'UUID public du locataire', format: 'uuid', example: 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d')]
        public string $tenantId,

        #[OA\Property(description: 'UUID public de l\'unité locative', format: 'uuid', example: 'a812bc34-912e-43a1-bb29-e81a09cd9112')]
        public string $unitId,

        #[OA\Property(description: 'Référence unique du contrat de bail', example: 'BAIL-2026-001')]
        public string $reference,

        #[OA\Property(description: 'Date de début du bail', format: 'date-time', example: '2026-01-01T00:00:00Z')]
        public \DateTimeImmutable $startDate,

        #[OA\Property(description: 'Date d\'échéance / fin du bail', format: 'date-time', example: '2026-12-31T23:59:59Z', nullable: true)]
        public ?\DateTimeImmutable $endDate,

        #[OA\Property(description: 'Montant du loyer mensuel convenu', example: '450.00')]
        public string $monthlyRent,

        #[OA\Property(description: 'Montant de la garantie locative / caution', example: '1350.00', nullable: true)]
        public ?string $depositAmount,

        #[OA\Property(description: 'Devise monétaire du bail', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Taux de change de référence pour le bail. Null si pas de conversion.', example: '2900.00000000', nullable: true)]
        public ?string $exchangeRate,

        #[OA\Property(description: 'Devise de référence pour la conversion. Null si pas de conversion.', type: 'string', example: 'USD', enum: Currency::class, nullable: true)]
        public ?Currency $referenceCurrency,

        #[OA\Property(description: 'Statut actuel du contrat de bail', type: 'string', example: 'active', enum: LeaseStatus::class)]
        public LeaseStatus $status,

        #[OA\Property(description: 'Date de résiliation anticipée ou effective', format: 'date-time', example: '2026-08-31T00:00:00Z', nullable: true)]
        public ?\DateTimeImmutable $terminationDate,

        #[OA\Property(description: 'Motif de résiliation du bail', example: 'Déménagement professionnel du locataire', nullable: true)]
        public ?string $terminationReason,

        #[OA\Property(description: 'Notes ou clauses particulières', example: 'Paiement avant le 05 de chaque mois', nullable: true)]
        public ?string $notes,

        #[OA\Property(description: 'Clauses contractuelles et conditions particulières du bail', nullable: true)]
        public ?string $terms,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-01T09:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-01-15T14:30:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Lease $lease): self
    {
        return new self(
            id: (string) $lease->getUuid(),
            organizationId: (string) $lease->getOrganization()->getUuid(),
            tenantId: (string) $lease->getTenant()->getUuid(),
            unitId: (string) $lease->getUnit()->getUuid(),
            reference: $lease->getReference(),
            startDate: $lease->getStartDate(),
            endDate: $lease->getEndDate(),
            monthlyRent: $lease->getMonthlyRent(),
            depositAmount: $lease->getDepositAmount(),
            currency: $lease->getCurrency(),
            exchangeRate: $lease->getExchangeRate(),
            referenceCurrency: $lease->getReferenceCurrency(),
            status: $lease->getStatus(),
            terminationDate: $lease->getTerminationDate(),
            terminationReason: $lease->getTerminationReason(),
            notes: $lease->getNotes(),
            terms: $lease->getTerms(),
            createdAt: $lease->getCreatedAt(),
            updatedAt: $lease->getUpdatedAt(),
        );
    }
}
