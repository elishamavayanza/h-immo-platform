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
 * Transforme la liaison OrganizationUser en objet DTO de réponse.
 * La projection vers le DTO est intégralement assurée par la fabrique
 * statique du DTO de réponse (identifiants des deux parents + rôle),
 * ce qui évite de dupliquer la liste des champs exposés.
 */
final readonly class OrganizationUserMapper
{
    public function toResponse(OrganizationUser $organizationUser): OrganizationUserResponse
    {
        return OrganizationUserResponse::fromEntity($organizationUser);
    }
}
