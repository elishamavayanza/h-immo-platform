<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ParcelRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une Parcelle (Parcel).
 */
#[OA\Schema(
    title: 'ParcelRequest',
    description: 'Payload pour l\'enregistrement ou la modification d\'une parcelle cadastrale.'
)]
final readonly class ParcelRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de la ville d\'implantation',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $cityUuid = null,

        #[OA\Property(
            description: 'Référence interne de la parcelle',
            example: 'PARC-2026-001',
            maxLength: 50
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Numéro de titre foncier / certificat d\'enregistrement',
            example: 'VOL-F-2415-FOL-89',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $titleNumber = null,

        #[OA\Property(
            description: 'Dénomination ou nom de la parcelle',
            example: 'Concession Les Oliviers',
            maxLength: 150
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        public ?string $name = null,

        #[OA\Property(
            description: 'Adresse physique ou numéro dans la rue',
            example: '14, Avenue du Lac',
            maxLength: 255
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $address = null,

        #[OA\Property(
            description: 'Quartier ou commune',
            example: 'Himbi',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $quarter = null,

        #[OA\Property(
            description: 'Superficie globale en m²',
            example: '1200.50'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $area = null,

        #[OA\Property(
            description: 'Coordonnée GPS : Latitude (-90 à 90)',
            example: '-1.678942',
            nullable: true
        )]
        #[Assert\Range(min: -90, max: 90, groups: ['create', 'update'])]
        public ?string $latitude = null,

        #[OA\Property(
            description: 'Coordonnée GPS : Longitude (-180 à 180)',
            example: '29.234123',
            nullable: true
        )]
        #[Assert\Range(min: -180, max: 180, groups: ['create', 'update'])]
        public ?string $longitude = null,

        #[OA\Property(
            description: 'Remarques ou détails complémentaires sur la parcelle',
            example: 'Parcelle clôturée avec accès direct à la voie principale.',
            nullable: true
        )]
        public ?string $description = null,
    ) {
    }
}
