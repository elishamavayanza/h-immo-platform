<?php

declare(strict_types=1);

namespace App\Dto\Response\Rental;

use App\Entity\Rental\Tenant;
use App\Enum\TenantType;
use OpenApi\Attributes as OA;

/**
 * TenantResponse
 *
 * Package : Rental Management — DTO de réponse
 */
#[OA\Schema(
    title: 'TenantResponse',
    description: 'Représentation publique d\'un locataire (personne physique ou morale).'
)]
final readonly class TenantResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public du locataire', format: 'uuid', example: 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation rattachée', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'Type de locataire (INDIVIDUAL ou COMPANY)', type: 'string', example: 'INDIVIDUAL', enum: TenantType::class)]
        public TenantType $type,

        #[OA\Property(description: 'Nom de famille (si personne physique)', example: 'Mukokoma', nullable: true)]
        public ?string $fullName,

        #[OA\Property(description: 'Raison sociale (si personne morale)', example: 'Kivu Services SARL', nullable: true)]
        public ?string $companyName,

        #[OA\Property(description: 'Numéro de téléphone principal', example: '+243990000000')]
        public string $phone,

        #[OA\Property(description: 'Adresse email de contact', example: 'locataire@example.com', nullable: true)]
        public ?string $email,

        #[OA\Property(description: 'Adresse de résidence ou siège social', example: '10, Avenue Maniema', nullable: true)]
        public ?string $address,

        #[OA\Property(description: 'Remarques ou historique sur le locataire', example: 'Locataire fiable depuis 2024', nullable: true)]
        public ?string $notes,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-05T10:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-02-12T15:45:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Tenant $tenant): self
    {
        return new self(
            id: (string) $tenant->getUuid(),
            organizationId: (string) $tenant->getOrganization()->getUuid(),
            type: $tenant->getType(),
            fullName: $tenant->getFullName(),
            companyName: $tenant->getCompanyName(),
            phone: $tenant->getPhone(),
            email: $tenant->getEmail(),
            address: $tenant->getAddress(),
            notes: $tenant->getNotes(),
            createdAt: $tenant->getCreatedAt(),
            updatedAt: $tenant->getUpdatedAt(),
        );
    }
}
