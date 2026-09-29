<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use App\Enum\OrganizationRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OrganizationUserRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Données entrantes pour rattacher un utilisateur à une organisation
 * avec un rôle donné. Les entités liées sont référencées par leur
 * UUID public (jamais par leur id technique interne).
 */
#[OA\Schema(
    title: 'OrganizationUserRequest',
    description: 'Payload pour assigner un rôle et un accès organisation à un utilisateur.'
)]
final readonly class OrganizationUserRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'organisation',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $organizationUuid = null,

        #[OA\Property(
            description: 'UUID public de l\'utilisateur à rattacher',
            format: 'uuid',
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $userUuid = null,

        #[OA\Property(
            description: 'Rôle attribué au sein de l\'organisation',
            type: 'string',
            example: 'admin_immobilier',
            enum: OrganizationRole::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?OrganizationRole $role = null,
    ) {
    }
}
