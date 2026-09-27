<?php

declare(strict_types=1);

namespace App\Mapper\Identity;

use App\Dto\Response\Identity\OrganizationUserResponse;
use App\Entity\Identity\OrganizationUser;

/**
 * OrganizationUserMapper
 *
 * Package : Identity & Access — Service de mapping d'entité
 *
 * Transforme les liaisons OrganizationUser en objet DTO de réponse
 * incluant les sous-DTOs des entités rattachées.
 */
final readonly class OrganizationUserMapper
{
    /**
     * Injecte les mappers dépendants pour construire la réponse complète.
     * Permet la conversion récursive des entités liées (Organization et User).
     */
    public function __construct(
        private OrganizationMapper $organizationMapper,
        private UserMapper $userMapper
    ) {
    }

    /**
     * Mappe l'entité de liaison OrganizationUser vers son DTO de réponse.
     * Convertit la relation N-N avec les données détaillées du rôle et des entités.
     */
    public function toResponse(OrganizationUser $organizationUser): OrganizationUserResponse
    {
        return new OrganizationUserResponse(
            uuid: $organizationUser->getUuid(),
            organization: $this->organizationMapper->toResponse($organizationUser->getOrganization()),
            user: $this->userMapper->toResponse($organizationUser->getUser()),
            role: $organizationUser->getRole(),
            createdAt: $organizationUser->getCreatedAt()
        );
    }
}
