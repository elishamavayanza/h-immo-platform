<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * ParcelRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une Parcelle (Parcel).
 *
 * Les coordonnées GPS (`latitude` / `longitude`) sont optionnelles mais
 * indivisibles : une latitude sans longitude (ou l'inverse) ne forme pas une
 * position exploitable par une carte et est donc refusée. La paire complète
 * est exigée ensemble, ou pas du tout.
 */
#[OA\Schema(
    title: 'ParcelRequest',
    description: 'Payload pour l\'enregistrement ou la modification d\'une parcelle cadastrale.'
)]
#[Assert\Callback(
    callback: 'validateCoordinates',
    groups: ['create', 'update'],
)]
final readonly class ParcelRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de la ville d\'implantation',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6',
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        #[Groups(['create'])]
        public ?string $cityUuid = null,

        #[OA\Property(
            description: 'Référence interne de la parcelle',
            example: 'PARC-2026-001',
            maxLength: 50,
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Numéro de titre foncier / certificat d\'enregistrement',
            example: 'VOL-F-2415-FOL-89',
            nullable: true,
            maxLength: 100,
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $titleNumber = null,

        #[OA\Property(
            description: 'Dénomination ou nom de la parcelle',
            example: 'Concession Les Oliviers',
            maxLength: 150,
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $name = null,

        #[OA\Property(
            description: 'Adresse physique ou numéro dans la rue',
            example: '14, Avenue du Lac',
            maxLength: 255,
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $address = null,

        #[OA\Property(
            description: 'Quartier ou commune',
            example: 'Himbi',
            nullable: true,
            maxLength: 100,
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $quarter = null,

        #[OA\Property(
            description: 'Superficie globale en m²',
            example: '1200.50',
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Positive(groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public ?string $area = null,

        #[OA\Property(
            description: 'Coordonnée GPS : Latitude (-90 à 90). Accepte un nombre ou sa représentation textuelle.',
            example: -0.681,
            nullable: true,
        )]
        #[Assert\Range(min: -90, max: 90, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public float|string|null $latitude = null,

        #[OA\Property(
            description: 'Coordonnée GPS : Longitude (-180 à 180). Accepte un nombre ou sa représentation textuelle.',
            example: 29.238,
            nullable: true,
        )]
        #[Assert\Range(min: -180, max: 180, groups: ['create', 'update'])]
        #[Groups(['create', 'update'])]
        public float|string|null $longitude = null,

        #[OA\Property(
            description: 'Remarques ou détails complémentaires sur la parcelle',
            example: 'Parcelle clôturée avec accès direct à la voie principale.',
            nullable: true,
        )]
        #[Groups(['create', 'update'])]
        public ?string $description = null,
    ) {
    }

    /**
     * Vérifie le caractère indivisible de la position GPS : latitude et
     * longitude doivent être fournies toutes les deux, ou aucune. Une
     * coordonnée isolée est rejetée, quelle que soit sa plage de validité.
     */
    public function validateCoordinates(ExecutionContextInterface $context): void
    {
        if (($this->latitude === null) === ($this->longitude === null)) {
            return;
        }

        $context->buildViolation(
            'Les coordonnées GPS doivent être fournies ensemble : latitude et longitude, ou bien aucune des deux.'
        )->addViolation();
    }
}
