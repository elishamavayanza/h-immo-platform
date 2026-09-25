<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Entity\Identity\User;
use App\Enum\PlatformRole;
use OpenApi\Attributes as OA;

/**
 * UserResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Représentation publique d'un User exposée par l'API. Ne contient
 * jamais `password`. Expose `id` comme l'UUID public de l'entité
 * (jamais la clé technique auto-incrémentée) — cf. BaseEntity.
 *
 * `fromEntity()` est un simple assembleur de données (mapping
 * entité -> DTO) : il ne contient aucune règle métier.
 */
#[OA\Schema(
    title: 'UserResponse',
    description: 'Représentation publique d\'un utilisateur de la plateforme.'
)]
final readonly class UserResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'utilisateur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $id,

        #[OA\Property(description: 'Adresse email unique', example: 'jean.kasereka@example.com')]
        public string $email,

        #[OA\Property(description: 'Prénom', example: 'Jean')]
        public string $fullName,

        #[OA\Property(description: 'Numéro de téléphone portable', example: '+243990000000', nullable: true)]
        public ?string $phone,

        #[OA\Property(description: 'URL de la photo de profil', example: 'https://cdn.example.com/profiles/jean.jpg', nullable: true)]
        public ?string $profilePhoto,

        #[OA\Property(description: 'Rôle global sur la plateforme', type: 'string', example: 'ROLE_USER', nullable: true, enum: PlatformRole::class)]
        public ?PlatformRole $platformRole,

        #[OA\Property(description: 'État du compte utilisateur', example: true)]
        public bool $isActive,

        #[OA\Property(description: 'Horodatage de la dernière connexion', format: 'date-time', example: '2026-03-01T09:12:00Z', nullable: true)]
        public ?\DateTimeImmutable $lastLoginAt,

        #[OA\Property(description: 'Horodatage de création du compte', format: 'date-time', example: '2026-01-05T12:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière modification', format: 'date-time', example: '2026-02-18T16:45:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (string) $user->getUuid(),
            email: $user->getEmail(),
            fullName: $user->getFullName(),
            phone: $user->getPhone(),
            profilePhoto: $user->getProfilePhoto(),
            platformRole: $user->getPlatformRole(),
            isActive: $user->isActive(),
            lastLoginAt: $user->getLastLoginAt(),
            createdAt: $user->getCreatedAt(),
            updatedAt: $user->getUpdatedAt(),
        );
    }
}
