<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Entity\Identity\UserCity;
use OpenApi\Attributes as OA;

/**
 * UserCityResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Représentation publique d'un rattachement User <-> City.
 */
#[OA\Schema(
    title: 'UserCityResponse',
    description: 'Représentation publique de l\'affectation d\'un gestionnaire à une ville.'
)]
final readonly class UserCityResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'affectation', format: 'uuid', example: 'd5e6f7a8-b9c0-1d2e-3f4a-5b6c7d8e9f0a')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'utilisateur', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $userId,

        #[OA\Property(description: 'UUID public de la ville attribuée', format: 'uuid', example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6')]
        public string $cityId,

        #[OA\Property(description: 'Horodatage de création du rattachement', format: 'date-time', example: '2026-02-10T14:20:00Z')]
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(UserCity $userCity): self
    {
        return new self(
            id: (string) $userCity->getUuid(),
            userId: (string) $userCity->getUser()->getUuid(),
            cityId: (string) $userCity->getCity()->getUuid(),
            createdAt: $userCity->getCreatedAt(),
        );
    }
}
