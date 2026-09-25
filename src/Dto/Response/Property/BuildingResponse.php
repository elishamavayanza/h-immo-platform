<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\Building;
use App\Enum\BuildingType;
use OpenApi\Attributes as OA;

/**
 * BuildingResponse
 *
 * Package : Property Management — DTO de réponse
 */
#[OA\Schema(
    title: 'BuildingResponse',
    description: 'Représentation publique d\'un bâtiment au sein d\'une parcelle.'
)]
final readonly class BuildingResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public du bâtiment', format: 'uuid', example: 'd3b07384-d113-4603-9c0e-a8946114421d')]
        public string $id,

        #[OA\Property(description: 'UUID public de la parcelle parente', format: 'uuid', example: '7f9c8112-9842-4e4d-b6a1-029d89a4401e')]
        public string $parcelId,

        #[OA\Property(description: 'Référence unique du bâtiment', example: 'BAT-A')]
        public string $reference,

        #[OA\Property(description: 'Nom usuel du bâtiment', example: 'Immeuble Matadi')]
        public string $name,

        #[OA\Property(description: 'Type de bâtiment', type: 'string', example: 'RESIDENTIAL', enum: BuildingType::class)]
        public BuildingType $type,

        #[OA\Property(description: 'Nombre de niveaux / étages', example: 4, nullable: true)]
        public ?int $numberOfFloors,

        #[OA\Property(description: 'Description complémentaire', example: 'Bâtiment à 4 niveaux avec ascenseur', nullable: true)]
        public ?string $description,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-10T08:30:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-02-15T11:00:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Building $building): self
    {
        return new self(
            id: (string) $building->getUuid(),
            parcelId: (string) $building->getParcel()->getUuid(),
            reference: $building->getReference(),
            name: $building->getName(),
            type: $building->getType(),
            numberOfFloors: $building->getNumberOfFloors(),
            description: $building->getDescription(),
            createdAt: $building->getCreatedAt(),
            updatedAt: $building->getUpdatedAt(),
        );
    }
}
