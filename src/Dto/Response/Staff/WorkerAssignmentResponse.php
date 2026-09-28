<?php

declare(strict_types=1);

namespace App\Dto\Response\Staff;

use App\Entity\Staff\WorkerAssignment;
use App\Enum\Currency;
use App\Enum\WorkerRole;
use OpenApi\Attributes as OA;

/**
 * WorkerAssignmentResponse
 *
 * Package : Staff Management — DTO de réponse
 */
#[OA\Schema(
    title: 'WorkerAssignmentResponse',
    description: 'Représentation publique d\'une affectation de travailleur.'
)]
final readonly class WorkerAssignmentResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'affectation', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $id,

        #[OA\Property(description: 'UUID public du travailleur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $workerId,

        #[OA\Property(description: 'UUID public de la ville d\'exercice', format: 'uuid', example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6')]
        public string $cityId,

        #[OA\Property(description: 'UUID public de la parcelle', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $parcelId,

        #[OA\Property(description: 'UUID public de l\'immeuble', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $buildingId,

        #[OA\Property(description: 'UUID public de l\'unité', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $unitId,

        #[OA\Property(description: 'Fonction exercée', type: 'string', example: 'gardien', enum: WorkerRole::class)]
        public WorkerRole $role,

        #[OA\Property(description: 'Rémunération mensuelle', example: '500.00')]
        public string $monthlySalary,

        #[OA\Property(description: 'Devise', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Date de début', format: 'date', example: '2026-01-01')]
        public \DateTimeImmutable $startDate,

        #[OA\Property(description: 'Date de fin', format: 'date', nullable: true, example: '2026-12-31')]
        public ?\DateTimeImmutable $endDate,

        #[OA\Property(description: 'Notes', nullable: true, example: 'Affectation temporaire')]
        public ?string $notes,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-03-15T10:30:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-03-15T14:20:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(WorkerAssignment $assignment): self
    {
        return new self(
            id: (string) $assignment->getUuid(),
            workerId: (string) $assignment->getWorker()->getUuid(),
            cityId: (string) $assignment->getCity()->getUuid(),
            parcelId: $assignment->getParcel() ? (string) $assignment->getParcel()->getUuid() : null,
            buildingId: $assignment->getBuilding() ? (string) $assignment->getBuilding()->getUuid() : null,
            unitId: $assignment->getUnit() ? (string) $assignment->getUnit()->getUuid() : null,
            role: $assignment->getRole(),
            monthlySalary: $assignment->getMonthlySalary(),
            currency: $assignment->getCurrency(),
            startDate: $assignment->getStartDate(),
            endDate: $assignment->getEndDate(),
            notes: $assignment->getNotes(),
            createdAt: $assignment->getCreatedAt(),
            updatedAt: $assignment->getUpdatedAt(),
        );
    }
}