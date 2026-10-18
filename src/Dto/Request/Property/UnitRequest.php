<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use App\Enum\Currency;
use App\Enum\UnitType;
use App\Enum\UnitOperationalStatus;
use Symfony\Component\Serializer\Annotation\Groups;
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
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d',
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        #[Groups(['create'])]
        public ?string $buildingUuid = null,

        #[OA\Property(description: 'UUID de l’espace parent pour modéliser une subdivision physique', format: 'uuid', nullable: true)]
        #[Assert\Uuid(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $parentUnitUuid = null,

        #[OA\Property(
            description: 'Référence ou numéro de porte/bureau',
            example: 'APT-102',
            maxLength: 50,
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Type d’unité (résidence, commerce, chambre, entrepôt, atelier, stockage, parking ou autre)',
            type: 'string',
            example: 'apartment',
            enum: UnitType::class,
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Groups(['create', 'update'])]
        public ?UnitType $type = null,

        #[OA\Property(description: 'État opérationnel; l’occupation reste calculée à partir des baux actifs', enum: UnitOperationalStatus::class)]
        #[Groups(['create', 'update'])]
        public ?UnitOperationalStatus $operationalStatus = null,

        #[OA\Property(description: 'Activité exercée dans un local commercial ou professionnel', maxLength: 150, nullable: true)]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $commercialActivity = null,

        #[OA\Property(
            description: 'Numéro de l\'étage (0 pour le rez-de-chaussée)',
            example: 1,
            minimum: 0,
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?int $floor = null,

        #[OA\Property(description: 'Libellé du niveau, par exemple rez-de-chaussée, mezzanine ou annexe arrière', maxLength: 100, nullable: true)]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $floorLabel = null,

        #[OA\Property(description: 'UUID du niveau déclaré pour le bâtiment', format: 'uuid', nullable: true)]
        #[Assert\Uuid(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $levelUuid = null,

        #[OA\Property(
            description: 'Surface habitable ou exploitable en m²',
            example: '85.50',
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Regex(pattern: '/^\d{1,6}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'La surface doit respecter la précision autorisée : 6 chiffres avant et 2 après la virgule.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $surface = null,

        #[OA\Property(
            description: 'Nombre de chambres à coucher',
            example: 2,
            nullable: true,
            minimum: 0,
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?int $bedrooms = null,

        #[OA\Property(
            description: 'Nombre total de pièces principales',
            example: 4,
            nullable: true,
            minimum: 0,
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?int $rooms = null,

        #[OA\Property(
            description: 'Nombre de salles de bain / d\'eau',
            example: 2,
            nullable: true,
            minimum: 0,
        )]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?int $bathrooms = null,

        #[OA\Property(
            description: 'Loyer mensuel hors charges',
            example: '450.00',
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'Le loyer doit être un nombre positif avec au plus 2 décimales.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $monthlyRent = null,

        #[OA\Property(
            description: 'Devise du loyer (USD, CDF)',
            type: 'string',
            example: 'USD',
            enum: Currency::class,
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Groups(['create', 'update'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Description détaillée du local et de ses équipements',
            example: 'Appartement lumineux avec balcons, cuisine équipée et compteur cash-power indépendant.',
            nullable: true,
            maxLength: 5000,
        )]
        #[Assert\Length(max: 5000, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $description = null,
    ) {
    }
}
