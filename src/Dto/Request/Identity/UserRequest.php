<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use App\Enum\PlatformRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UserRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Représente les données entrantes (body JSON) pour la création ou la
 * mise à jour d'un User.
 */
#[OA\Schema(
    title: 'UserRequest',
    description: 'Données requises pour créer ou modifier un compte utilisateur sur la plateforme.'
)]
final readonly class UserRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Adresse email unique de l\'utilisateur',
            example: 'jean.kasereka@example.com',
            maxLength: 180
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Email(groups: ['create', 'update'])]
        #[Assert\Length(max: 180, groups: ['create', 'update'])]
        public ?string $email = null,

        #[OA\Property(
            description: 'Mot de passe en clair (obligatoire à la création, optionnel à la mise à jour)',
            example: 'P@ssword2026!',
            nullable: true,
            maxLength: 255,
            minLength: 8
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(min: 8, max: 255, groups: ['create', 'update'])]
        public ?string $password = null,

        #[OA\Property(
            description: 'Prénom de l\'utilisateur',
            example: 'Jean',
            maxLength: 100
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $firstName = null,

        #[OA\Property(
            description: 'Nom de famille de l\'utilisateur',
            example: 'Kasereka',
            maxLength: 100
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $lastName = null,

        #[OA\Property(
            description: 'Numéro de téléphone portable',
            example: '+243990000000',
            nullable: true,
            maxLength: 30
        )]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $phone = null,

        #[OA\Property(
            description: 'URL ou chemin vers la photo de profil',
            example: 'https://cdn.example.com/profiles/jean.jpg',
            nullable: true,
            maxLength: 255
        )]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $profilePhoto = null,

        #[OA\Property(
            description: 'Rôle au niveau global de la plateforme. Seul `super_admin` existe, et il est réservé à la plateforme : laisser `null` pour un compte d\'organisation.',
            type: 'string',
            example: null,
            nullable: true,
            enum: PlatformRole::class
        )]
        public ?PlatformRole $platformRole = null,

        #[OA\Property(
            description: 'Indique si le compte est actif ou suspendu',
            example: true,
            default: true
        )]
        #[Assert\Type('bool', groups: ['create', 'update'])]
        public bool $isActive = true,
    ) {
    }
}
