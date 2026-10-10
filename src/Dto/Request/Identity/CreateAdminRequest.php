<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use App\Enum\OrganizationRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CreateAdminRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Données pour la création d'un administrateur par un utilisateur autorisé.
 *
 * Le mot de passe n'est PAS fourni : l'utilisateur le définira via le flux
 * "mot de passe oublié" après réception de l'email d'activation. Un ADMIN_IMMOBILIER
 * ne peut créer que le rôle ADMIN_VILLE, limité aux villes transmises.
 */
#[OA\Schema(
    title: 'CreateAdminRequest',
    description: 'Création d\'un administrateur selon le rôle de l\'appelant.'
)]
final readonly class CreateAdminRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID de l\'organisation cible, vérifié côté serveur',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $organizationUuid,

        #[OA\Property(
            // La liste est explicitement restreinte aux rôles qu'un PATRON
            // peut déléguer : `enum: OrganizationRole::class` annoncerait aussi
            // `patron`, que `Assert\Choice` refuse. « Try it out » proposerait
            // donc une valeur rejetée en 422.
            description: 'Rôle à attribuer (admin_immobilier ou admin_ville). Un ADMIN_IMMOBILIER appelant ne peut créer qu’un ADMIN_VILLE.',
            type: 'string',
            example: 'admin_immobilier',
            enum: [OrganizationRole::ADMIN_IMMOBILIER->value, OrganizationRole::ADMIN_VILLE->value]
        )]
        #[Assert\NotBlank]
        #[Assert\Choice(choices: [OrganizationRole::ADMIN_IMMOBILIER, OrganizationRole::ADMIN_VILLE])]
        public OrganizationRole $role,

        #[OA\Property(
            description: 'Email de l\'administrateur (sera son identifiant de connexion)',
            example: 'admin@immo-rdc.cd',
            maxLength: 180
        )]
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email,

        #[OA\Property(
            description: 'Nom complet de l\'administrateur',
            example: 'Jean Dupont',
            maxLength: 200
        )]
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public string $fullName,

        #[OA\Property(
            description: 'Téléphone de l\'administrateur',
            example: '+243990000002',
            maxLength: 30
        )]
        #[Assert\NotBlank]
        #[Assert\Length(max: 30)]
        public string $phone,

        #[OA\Property(
            description: 'UUIDs des villes assignées (requis pour ADMIN_VILLE, ignoré pour ADMIN_IMMOBILIER)',
            type: 'array',
            items: new OA\Items(type: 'string', format: 'uuid'),
            example: ['c3019a82-3ad4-4861-a53c-1123a1a3b110']
        )]
        #[Assert\Count(min: 1, minMessage: 'Au moins une ville doit être assignée pour un ADMIN_VILLE.')]
        public ?array $cityUuids = null,
    ) {
    }
}
