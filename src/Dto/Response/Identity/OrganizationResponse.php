<?php

declare(strict_types=1);

namespace App\Dto\Response\Identity;

use App\Entity\Identity\Organization;
use App\Enum\OrganizationStatus;
use OpenApi\Attributes as OA;

/**
 * OrganizationResponse
 *
 * Package : Identity & Access — DTO de réponse
 *
 * Représentation publique d'une Organization exposée par l'API.
 */
#[OA\Schema(
    title: 'OrganizationResponse',
    description: 'Représentation publique d\'une organisation cliente.'
)]
final readonly class OrganizationResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'organisation', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $id,

        #[OA\Property(description: 'Nom officiel de l\'organisation', example: 'Immo RDC SARL')]
        public string $name,

        #[OA\Property(description: 'Code unique identifiant l\'organisation', example: 'IMMO-RDC')]
        public string $code,

        #[OA\Property(description: 'URL du logo de l\'organisation', example: 'https://cdn.example.com/logos/immo-rdc.png', nullable: true)]
        public ?string $logo,

        #[OA\Property(description: 'Adresse email principale', example: 'contact@immo-rdc.cd')]
        public string $email,

        #[OA\Property(description: 'Numéro de téléphone officiel', example: '+243990000000')]
        public string $phone,

        #[OA\Property(description: 'Adresse physique du siège social', example: '124, Avenue Katindo', nullable: true)]
        public ?string $address,

        #[OA\Property(description: 'Ville du siège social', example: 'Goma', nullable: true)]
        public ?string $city,

        #[OA\Property(description: 'Pays du siège social', example: 'RDC', nullable: true)]
        public ?string $country,

        #[OA\Property(description: 'Statut de l\'organisation', type: 'string', example: 'active', enum: OrganizationStatus::class)]
        public OrganizationStatus $status,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-01-15T08:30:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-02-01T10:15:00Z')]
        public \DateTimeImmutable $updatedAt,

        #[OA\Property(description: 'Identifiant de la page publique (slug) de la vitrine', example: 'immo-plus', nullable: true)]
        public ?string $slug,

        #[OA\Property(description: 'Texte de présentation publique de l\'entreprise', nullable: true)]
        public ?string $publicDescription,

        #[OA\Property(description: 'La vitrine est-elle listée publiquement', example: true)]
        public bool $isPubliclyListed,
        #[OA\Property(description: 'La fonctionnalité vitrine est-elle autorisée par la plateforme', example: false)]
        public bool $showcaseEnabled,
        #[OA\Property(description: 'Nombre maximal de villes ; null indique sans limite', nullable: true)]
        public ?int $maxCities,
        #[OA\Property(description: 'Nombre maximal de parcelles ; null indique sans limite', nullable: true)]
        public ?int $maxParcels,
        #[OA\Property(description: 'Nombre maximal de bâtiments ; null indique sans limite', nullable: true)]
        public ?int $maxBuildings,
        #[OA\Property(description: 'Nombre maximal d’unités ; null indique sans limite', nullable: true)]
        public ?int $maxUnits,
        #[OA\Property(description: 'Nombre maximal de membres ; null indique sans limite', nullable: true)]
        public ?int $maxMembers,
        #[OA\Property(description: 'Fin de la suspension temporaire ; null si indéterminée ou organisation active.', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $suspensionEndsAt,
    ) {
    }

    public static function fromEntity(Organization $organization): self
    {
        return new self(
            id: (string) $organization->getUuid(),
            name: $organization->getName(),
            code: $organization->getCode(),
            logo: $organization->getLogo(),
            email: $organization->getEmail(),
            phone: $organization->getPhone(),
            address: $organization->getAddress(),
            city: $organization->getCity(),
            country: $organization->getCountry(),
            status: $organization->getStatus(),
            createdAt: $organization->getCreatedAt(),
            updatedAt: $organization->getUpdatedAt(),
            slug: $organization->getSlug(),
            publicDescription: $organization->getPublicDescription(),
            isPubliclyListed: $organization->isPubliclyListed(),
            showcaseEnabled: $organization->isShowcaseEnabled(),
            maxCities: $organization->getMaxCities(),
            maxParcels: $organization->getMaxParcels(),
            maxBuildings: $organization->getMaxBuildings(),
            maxUnits: $organization->getMaxUnits(),
            maxMembers: $organization->getMaxMembers(),
            suspensionEndsAt: $organization->getSuspensionEndsAt(),
        );
    }
}
