<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\City;
use App\Enum\CityStatus;
use OpenApi\Attributes as OA;

/**
 * CityResponse
 *
 * Package : Property Management — DTO de réponse
 */
#[OA\Schema(
    title: 'CityResponse',
    description: 'Représentation publique d\'une ville gérée dans le système.'
)]
final readonly class CityResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de la ville', format: 'uuid', example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation rattachée', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'Nom officiel de la ville', example: 'Goma')]
        public string $name,

        #[OA\Property(description: 'Code identifiant de la ville', example: 'GOM')]
        public string $code,

        #[OA\Property(description: 'Province ou région', example: 'Nord-Kivu', nullable: true)]
        public ?string $province,

        #[OA\Property(description: 'Nom du pays', example: 'République Démocratique du Congo', nullable: true)]
        public ?string $country,

        #[OA\Property(description: 'Statut de la ville dans le système', type: 'string', example: 'active', enum: CityStatus::class)]
        public CityStatus $status,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-01T00:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-01-01T00:00:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(City $city): self
    {
        return new self(
            id: (string) $city->getUuid(),
            organizationId: (string) $city->getOrganization()->getUuid(),
            name: $city->getName(),
            code: $city->getCode(),
            province: $city->getProvince(),
            country: $city->getCountry(),
            status: $city->getStatus(),
            createdAt: $city->getCreatedAt(),
            updatedAt: $city->getUpdatedAt(),
        );
    }
}
