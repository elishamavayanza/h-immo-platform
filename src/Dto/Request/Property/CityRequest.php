<?php

declare(strict_types=1);

namespace App\Dto\Request\Property;

use App\Enum\CityStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CityRequest
 *
 * Package : Property Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une City.
 */
#[OA\Schema(
    title: 'CityRequest',
    description: 'Payload pour la création ou la mise à jour d\'une ville d\'exploitation.'
)]
final readonly class CityRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'organisation rattachée (uniquement à la création)',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $organizationUuid = null,

        #[OA\Property(
            description: 'Nom de la ville',
            example: 'Goma',
            maxLength: 100
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $name = null,

        #[OA\Property(
            description: 'Code ou trigramme de la ville',
            example: 'GOM',
            maxLength: 30
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $code = null,

        #[OA\Property(
            description: 'Province ou région',
            example: 'Nord-Kivu',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $province = null,

        #[OA\Property(
            description: 'Pays',
            example: 'RDC',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $country = null,

        #[OA\Property(
            description: 'Statut de la ville (ACTIVE, INACTIVE)',
            type: 'string',
            example: 'ACTIVE',
            enum: CityStatus::class
        )]
        public CityStatus $status = CityStatus::ACTIVE,
    ) {
    }
}
