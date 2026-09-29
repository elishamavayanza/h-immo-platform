<?php

declare(strict_types=1);

namespace App\Dto\Response\Rental;

use App\Entity\Rental\Rent;
use App\Enum\Currency;
use App\Enum\RentStatus;
use OpenApi\Attributes as OA;

/**
 * RentResponse
 *
 * Package : Rental Management — DTO de réponse
 *
 * Le champ `status` reflète le statut CALCULÉ (computed) incluant
 * OVERDUE évalué à la volée. Le statut persistant en base ne contient
 * jamais OVERDUE.
 */
#[OA\Schema(
    title: 'RentResponse',
    description: 'Représentation publique d\'un appel de loyer / terme à échoir.'
)]
final readonly class RentResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'échéance de loyer', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $id,

        #[OA\Property(description: 'UUID public du contrat de bail associé', format: 'uuid', example: 'e3b0c442-98fc-4282-9a3b-2b0d7b3dcb6d')]
        public string $leaseId,

        #[OA\Property(description: 'Mois et année concernés par l\'échéance', format: 'date-time', example: '2026-03-01T00:00:00Z')]
        public \DateTimeImmutable $period,

        #[OA\Property(description: 'Date limite d\'exigibilité du paiement', format: 'date-time', example: '2026-03-05T23:59:59Z')]
        public \DateTimeImmutable $dueDate,

        #[OA\Property(description: 'Montant total exigé pour la période', example: '450.00')]
        public string $amount,

        #[OA\Property(description: 'Devise monétaire', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Statut du loyer (PAID, PENDING, OVERDUE, PARTIALLY_PAID) — calculé à la volée', type: 'string', example: 'overdue', enum: RentStatus::class)]
        public RentStatus $status,

        #[OA\Property(description: 'Indique si l\'échéance est en retard (impayée) à la date du jour', example: true)]
        public bool $isOverdue,

        #[OA\Property(description: 'Horodatage de création de l\'échéance', format: 'date-time', example: '2026-03-01T00:05:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-03-02T10:16:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Rent $rent, ?string $paidAmount = null): self
    {
        return new self(
            id: (string) $rent->getUuid(),
            leaseId: (string) $rent->getLease()->getUuid(),
            period: $rent->getPeriod(),
            dueDate: $rent->getDueDate(),
            amount: $rent->getAmount(),
            currency: $rent->getCurrency(),
            status: $rent->getComputedStatus(),
            isOverdue: $rent->isOverdue(),
            createdAt: $rent->getCreatedAt(),
            updatedAt: $rent->getUpdatedAt(),
        );
    }
}
