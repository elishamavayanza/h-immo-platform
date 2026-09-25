<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Entity\Identity\OrganizationUser;
use App\Enum\OrganizationRole;
use OpenApi\Attributes as OA;

/**
 * OrganizationUserResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Représentation publique d'un rattachement User <-> Organization.
 * Les entités liées sont référencées par leur UUID, pas imbriquées
 * intégralement, afin d'éviter les réponses trop profondes/lourdes ;
 * charge au client d'appeler les endpoints dédiés si besoin de détail.
 */
#[OA\Schema(
    title: 'OrganizationUserResponse',
    description: 'Représentation publique du rattachement entre un utilisateur et une organisation.'
)]
final readonly class OrganizationUserResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'association', format: 'uuid', example: 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'UUID public de l\'utilisateur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $userId,

        #[OA\Property(description: 'Rôle attribué dans l\'organisation', type: 'string', example: 'ORG_ADMIN', enum: OrganizationRole::class)]
        public OrganizationRole $role,

        #[OA\Property(description: 'Horodatage de création de l\'association', format: 'date-time', example: '2026-01-20T11:00:00Z')]
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(OrganizationUser $organizationUser): self
    {
        return new self(
            id: (string) $organizationUser->getUuid(),
            organizationId: (string) $organizationUser->getOrganization()->getUuid(),
            userId: (string) $organizationUser->getUser()->getUuid(),
            role: $organizationUser->getRole(),
            createdAt: $organizationUser->getCreatedAt(),
        );
    }
}
