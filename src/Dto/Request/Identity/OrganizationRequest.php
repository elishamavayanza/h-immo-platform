<?php

declare(strict_types=1);

namespace App\Dto\Request\Identity;

use App\Enum\OrganizationStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * OrganizationRequest
 *
 * Package : Identity & Access — DTO de requête
 *
 * Représente le payload entrant pour la création ou la mise à jour d'une Organisation.
 */
#[OA\Schema(
    title: 'OrganizationRequest',
    description: 'Données requises pour créer ou modifier une entreprise cliente (tenant).'
)]
final readonly class OrganizationRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Nom officiel de l\'organisation',
            example: 'Immo RDC SARL',
            maxLength: 150
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 150, groups: ['create', 'update'])]
        public ?string $name = null,

        #[OA\Property(
            description: 'Code unique identifiant l\'organisation (utilisé pour l\'isolation multi-tenant)',
            example: 'IMMO-RDC',
            maxLength: 30
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $code = null,

        #[OA\Property(
            description: 'URL ou chemin d\'accès du logo de l\'organisation',
            example: 'https://cdn.example.com/logos/immo-rdc.png',
            nullable: true,
            maxLength: 255
        )]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $logo = null,

        #[OA\Property(
            description: 'Adresse email principale de contact de l\'organisation',
            example: 'contact@immo-rdc.cd',
            maxLength: 180
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Email(groups: ['create', 'update'])]
        #[Assert\Length(max: 180, groups: ['create', 'update'])]
        public ?string $email = null,

        #[OA\Property(
            description: 'Numéro de téléphone officiel',
            example: '+243990000000',
            maxLength: 30
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $phone = null,

        #[OA\Property(
            description: 'Adresse physique du siège social',
            example: '124, Avenue Katindo',
            nullable: true,
            maxLength: 255
        )]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $address = null,

        #[OA\Property(
            description: 'Ville où se situe le siège social',
            example: 'Goma',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $city = null,

        #[OA\Property(
            description: 'Pays du siège social',
            example: 'RDC',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $country = null,

        #[OA\Property(
            description: 'Statut de l\'organisation (ACTIVE, SUSPENDED, INACTIVE)',
            type: 'string',
            example: 'active',
            enum: [OrganizationStatus::ACTIVE, OrganizationStatus::SUSPENDED, OrganizationStatus::INACTIVE]
        )]
        public OrganizationStatus $status = OrganizationStatus::ACTIVE,

        // --- Champs pour la création du PATRON (utilisateur responsable) ---
        #[OA\Property(
            description: 'Email du PATRON (sera son identifiant de connexion)',
            example: 'patron@immo-rdc.cd',
            maxLength: 180
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Email(groups: ['create'])]
        #[Assert\Length(max: 180, groups: ['create'])]
        public ?string $patronEmail = null,

        #[OA\Property(
            description: 'Nom complet du PATRON',
            example: 'Jean Dupont',
            maxLength: 200
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 200, groups: ['create'])]
        public ?string $patronFullName = null,

        #[OA\Property(
            description: 'Téléphone du PATRON',
            example: '+243990000001',
            maxLength: 30
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 30, groups: ['create'])]
        public ?string $patronPhone = null
    ) {
    }
}
