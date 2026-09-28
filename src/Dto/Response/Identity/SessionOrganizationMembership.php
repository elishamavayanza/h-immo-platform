<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Enum\OrganizationRole;
use OpenApi\Attributes as OA;

/**
 * SessionOrganizationMembership
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Rôle métier d'un utilisateur dans une Organization.
 *
 * L'UUID et le code de l'Organization sont exposés : c'est le couple
 * qu'un client doit fournir ensuite pour cibler une Organization dans les
 * requêtes métier, et cela évite un aller-retour supplémentaire.
 */
#[OA\Schema(
    title: 'SessionOrganizationMembership',
    description: 'Rôle métier d\'un utilisateur au sein d\'une Organization.'
)]
final readonly class SessionOrganizationMembership
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'Organization', format: 'uuid', example: '7b2e0d1a-4c3f-4a2b-9e1d-5c6f7a8b9c0d')]
        public string $uuid,

        #[OA\Property(description: 'Code de l\'Organization', example: 'AGENCE-KINSHASA')]
        public string $code,

        #[OA\Property(description: 'Nom de l\'Organization', example: 'Agence Immobilière de Kinshasa')]
        public string $name,

        #[OA\Property(description: 'Rôle métier dans cette Organization', type: 'string', enum: OrganizationRole::class, example: OrganizationRole::PATRON)]
        public OrganizationRole $role,
    ) {
    }
}
