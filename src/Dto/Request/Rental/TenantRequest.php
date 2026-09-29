<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\TenantType;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TenantRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un locataire (Tenant).
 */
#[OA\Schema(
    title: 'TenantRequest',
    description: 'Payload pour la création ou la modification de la fiche d\'un locataire (personne physique ou morale).'
)]
final readonly class TenantRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'organisation rattachée',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $organizationUuid = null,

        #[OA\Property(
            description: 'Type de locataire (INDIVIDUAL ou COMPANY)',
            type: 'string',
            example: 'individual',
            enum: TenantType::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?TenantType $type = null,

        #[OA\Property(
            description: 'Prénom (requis si type = INDIVIDUAL)',
            example: 'Amani',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $firstName = null,

        #[OA\Property(
            description: 'Nom de famille (requis si type = INDIVIDUAL)',
            example: 'Kambale',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $lastName = null,

        #[OA\Property(
            description: 'Raison sociale / Nom de l\'entreprise (requis si type = COMPANY)',
            example: 'Kivu Tech SARL',
            nullable: true,
            maxLength: 150
        )]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        public ?string $companyName = null,

        #[OA\Property(
            description: 'Numéro de téléphone principal',
            example: '+243990000000',
            maxLength: 30
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $phone = null,

        #[OA\Property(
            description: 'Adresse email du locataire',
            example: 'amani.kambale@example.cd',
            nullable: true,
            maxLength: 180
        )]
        #[Assert\Email(groups: ['create', 'update'])]
        #[Assert\Length(max: 180, groups: ['create', 'update'])]
        public ?string $email = null,

        #[OA\Property(
            description: 'Adresse physique / Domicile légal',
            example: '05, Avenue du Centre, Quartier Les Volcans',
            nullable: true,
            maxLength: 255
        )]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $address = null,

        #[OA\Property(
            description: 'Notes et observations administratives',
            example: 'Locataire fiable. Garant physique identifié.',
            nullable: true
        )]
        public ?string $notes = null,
    ) {
    }
}
