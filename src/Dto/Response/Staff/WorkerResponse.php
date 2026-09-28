<?php

declare(strict_types=1);

namespace App\Dto\Response\Staff;

use App\Entity\Staff\Worker;
use App\Entity\Identity\Organization;
use OpenApi\Attributes as OA;

/**
 * WorkerResponse
 *
 * Package : Staff Management — DTO de réponse
 */
#[OA\Schema(
    title: 'WorkerResponse',
    description: 'Représentation publique d\'un travailleur.'
)]
final readonly class WorkerResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public du travailleur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation', format: 'uuid', example: 'e3b0c442-98fc-4282-9a3b-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'Nom complet', maxLength: 200, example: 'Jean Dupont')]
        public string $fullName,

        #[OA\Property(description: 'Téléphone', maxLength: 30, example: '+243 99 123 4567')]
        public string $phone,

        #[OA\Property(description: 'Email', format: 'email', maxLength: 180, nullable: true, example: 'jean.dupont@example.com')]
        public ?string $email,

        #[OA\Property(description: 'Numéro d\'identification nationale', maxLength: 50, nullable: true, example: '123456789')]
        public ?string $nationalId,

        #[OA\Property(description: 'Adresse de résidence', maxLength: 255, nullable: true, example: '123 Avenue des Martyrs')]
        public ?string $address,

        #[OA\Property(description: 'Notes', nullable: true, example: 'Disponible pour affectations multiples')]
        public ?string $notes,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-03-15T10:30:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-03-15T14:20:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Worker $worker): self
    {
        return new self(
            id: (string) $worker->getUuid(),
            organizationId: (string) $worker->getOrganization()->getUuid(),
            fullName: $worker->getFullName(),
            phone: $worker->getPhone(),
            email: $worker->getEmail(),
            nationalId: $worker->getNationalId(),
            address: $worker->getAddress(),
            notes: $worker->getNotes(),
            createdAt: $worker->getCreatedAt(),
            updatedAt: $worker->getUpdatedAt(),
        );
    }
}