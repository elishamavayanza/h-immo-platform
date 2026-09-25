<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use App\Enum\Currency;
use App\Enum\UnitType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UnitRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un Local / Unité locative (Unit).
 */
#[OA\Schema(
    title: 'UnitRequest',
    description: 'Payload pour la création ou la mise à jour d\'un local locatif (appartement, bureau, magasin, etc.).'
)]
final readonly class UnitRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public du bâtiment contenant cette unité',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $buildingUuid = null,

        #[OA\Property(
            description: 'Référence ou numéro de porte/bureau',
            example: 'APT-102',
            maxLength: 50
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Type d\'unité (APARTMENT, OFFICE, COMMERCIAL_STORE, WAREHOUSE, etc.)',
            type: 'string',
            example: 'APARTMENT',
            enum: UnitType::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?UnitType $type = null,

        #[OA\Property(
            description: 'Numéro de l\'étage (0 pour le rez-de-chaussée)',
            example: 1,
            minimum: 0
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?int $floor = null,

        #[OA\Property(
            description: 'Surface habitable ou exploitable en m²',
            example: '85.50'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $surface = null,

        #[OA\Property(
            description: 'Nombre de chambres à coucher',
            example: 2,
            nullable: true,
            minimum: 0
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?int $bedrooms = null,

        #[OA\Property(
            description: 'Nombre total de pièces principales',
            example: 4,
            nullable: true,
            minimum: 0
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?int $rooms = null,

        #[OA\Property(
            description: 'Nombre de salles de bain / d\'eau',
            example: 2,
            nullable: true,
            minimum: 0
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?int $bathrooms = null,

        #[OA\Property(
            description: 'Loyer mensuel hors charges',
            example: '450.00'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $monthlyRent = null,

        #[OA\Property(
            description: 'Devise du loyer (USD, CDF, EUR)',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Description détaillée du local et de ses équipements',
            example: 'Appartement lumineux avec balcons, cuisine équipée et compteur cash-power indépendant.',
            nullable: true
        )]
        public ?string $description = null,
    ) {
    }
}
