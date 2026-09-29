<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\Unit;
use App\Enum\Currency;
use App\Enum\UnitType;
use OpenApi\Attributes as OA;

/**
 * UnitResponse
 *
 * Package : Property Management — DTO de réponse
 */
#[OA\Schema(
    title: 'UnitResponse',
    description: 'Représentation publique d\'une unité locative (appartement, bureau, magasin, etc.).'
)]
final readonly class UnitResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'unité', format: 'uuid', example: 'a812bc34-912e-43a1-bb29-e81a09cd9112')]
        public string $id,

        #[OA\Property(description: 'UUID public du bâtiment parent', format: 'uuid', example: 'd3b07384-d113-4603-9c0e-a8946114421d')]
        public string $buildingId,

        #[OA\Property(description: 'Référence unique de l\'unité', example: 'APT-101')]
        public string $reference,

        #[OA\Property(description: 'Type d\'unité locative', type: 'string', example: 'apartment', enum: UnitType::class)]
        public UnitType $type,

        #[OA\Property(description: 'Numéro d\'étage (0 pour Rez-de-chaussée)', example: 1)]
        public int $floor,

        #[OA\Property(description: 'Surface utile en m2', example: '75.50')]
        public string $surface,

        #[OA\Property(description: 'Nombre de chambres à coucher', example: 2, nullable: true)]
        public ?int $bedrooms,

        #[OA\Property(description: 'Nombre total de pièces', example: 4, nullable: true)]
        public ?int $rooms,

        #[OA\Property(description: 'Nombre de salles de bain / d\'eau', example: 1, nullable: true)]
        public ?int $bathrooms,

        #[OA\Property(description: 'Montant du loyer mensuel de base', example: '450.00')]
        public string $monthlyRent,

        #[OA\Property(description: 'Devise monétaire du loyer', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Description détaillée des équipements ou spécificités', example: 'Vue panoramique sur le lac, balcons inclus', nullable: true)]
        public ?string $description,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-15T10:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-03-01T16:20:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Unit $unit): self
    {
        return new self(
            id: (string) $unit->getUuid(),
            buildingId: (string) $unit->getBuilding()->getUuid(),
            reference: $unit->getReference(),
            type: $unit->getType(),
            floor: $unit->getFloor(),
            surface: $unit->getSurface(),
            bedrooms: $unit->getBedrooms(),
            rooms: $unit->getRooms(),
            bathrooms: $unit->getBathrooms(),
            monthlyRent: $unit->getMonthlyRent(),
            currency: $unit->getCurrency(),
            description: $unit->getDescription(),
            createdAt: $unit->getCreatedAt(),
            updatedAt: $unit->getUpdatedAt(),
        );
    }
}
