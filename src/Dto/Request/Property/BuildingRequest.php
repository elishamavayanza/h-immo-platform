<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use App\Enum\BuildingType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * BuildingRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un Immeuble (Building).
 */
#[OA\Schema(
    title: 'BuildingRequest',
    description: 'Payload pour la création ou la mise à jour d\'un immeuble/bâtiment sur une parcelle.'
)]
final readonly class BuildingRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de la parcelle associée',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $parcelUuid = null,

        #[OA\Property(
            description: 'Référence ou code interne du bâtiment',
            example: 'BLD-A1',
            maxLength: 50
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Nom du bâtiment ou de la résidence',
            example: 'Bâtiment Principal A',
            maxLength: 150
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        public ?string $name = null,

        #[OA\Property(
            description: 'Type de bâtiment (COMMERCIAL, RESIDENTIAL, MIXED, etc.)',
            type: 'string',
            example: 'RESIDENTIAL',
            enum: BuildingType::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?BuildingType $type = null,

        #[OA\Property(
            description: 'Nombre total d\'étages',
            example: 4,
            nullable: true,
            minimum: 0
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?int $numberOfFloors = null,

        #[OA\Property(
            description: 'Description détaillée du bâtiment',
            example: 'Immeuble moderne de 4 niveaux avec ascenseur et parking souterrain.',
            nullable: true
        )]
        public ?string $description = null,
    ) {
    }
}
