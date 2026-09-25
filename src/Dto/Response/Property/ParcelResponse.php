<?php

declare(strict_types=1);

namespace App\Dto\Response\Property;

use App\Entity\Property\Parcel;
use OpenApi\Attributes as OA;

/**
 * ParcelResponse
 *
 * Package : Property Management — DTO de réponse
 */
#[OA\Schema(
    title: 'ParcelResponse',
    description: 'Représentation publique d\'une parcelle foncière.'
)]
final readonly class ParcelResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de la parcelle', format: 'uuid', example: '7f9c8112-9842-4e4d-b6a1-029d89a4401e')]
        public string $id,

        #[OA\Property(description: 'UUID public de la ville parente', format: 'uuid', example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6')]
        public string $cityId,

        #[OA\Property(description: 'Référence interne de la parcelle', example: 'PAR-GOM-001')]
        public string $reference,

        #[OA\Property(description: 'Numéro de titre foncier / certificat d\'enregistrement', example: 'TF-12984-NK', nullable: true)]
        public ?string $titleNumber,

        #[OA\Property(description: 'Nom attribué à la parcelle', example: 'Concession Les Palmiers')]
        public string $name,

        #[OA\Property(description: 'Adresse physique principale', example: '15, Avenue du Lac, Q. Himbi')]
        public string $address,

        #[OA\Property(description: 'Quartier ou secteur local', example: 'Himbi', nullable: true)]
        public ?string $quarter,

        #[OA\Property(description: 'Superficie globale exprimée sous forme textuelle ou décimale', example: '450.00')]
        public string $area,

        #[OA\Property(description: 'Coordonnée GPS : Latitude', example: '-1.67891200', nullable: true)]
        public ?string $latitude,

        #[OA\Property(description: 'Coordonnée GPS : Longitude', example: '29.23145600', nullable: true)]
        public ?string $longitude,

        #[OA\Property(description: 'Description détaillée', example: 'Parcelle clôturée au bord du lac Kivu', nullable: true)]
        public ?string $description,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-12T09:00:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-02-10T14:30:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Parcel $parcel): self
    {
        return new self(
            id: (string) $parcel->getUuid(),
            cityId: (string) $parcel->getCity()->getUuid(),
            reference: $parcel->getReference(),
            titleNumber: $parcel->getTitleNumber(),
            name: $parcel->getName(),
            address: $parcel->getAddress(),
            quarter: $parcel->getQuarter(),
            area: $parcel->getArea(),
            latitude: $parcel->getLatitude(),
            longitude: $parcel->getLongitude(),
            description: $parcel->getDescription(),
            createdAt: $parcel->getCreatedAt(),
            updatedAt: $parcel->getUpdatedAt(),
        );
    }
}
