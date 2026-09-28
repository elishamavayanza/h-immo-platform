<?php

declare(strict_types=1);

namespace App\Dto\Request\Staff;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WorkerRequest
 *
 * Package : Staff Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un travailleur.
 *
 * L'Organization est déduite de l'utilisateur connecté (PATRON/ADMIN_IMMOBILIER)
 * ou de la ville fournie (ADMIN_VILLE). Elle n'est jamais fournie par le client.
 */
#[OA\Schema(
    title: 'WorkerRequest',
    description: 'Payload pour la création ou la mise à jour d\'un travailleur.'
)]
final readonly class WorkerRequest
{
    public function __construct(
        #[OA\Property(
            description: 'Nom complet du travailleur',
            maxLength: 200,
            example: 'Jean Dupont'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 200, groups: ['create', 'update'])]
        public ?string $fullName = null,

        #[OA\Property(
            description: 'Numéro de téléphone',
            maxLength: 30,
            example: '+243 99 123 4567'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Length(max: 30, groups: ['create', 'update'])]
        public ?string $phone = null,

        #[OA\Property(
            description: 'Adresse électronique',
            format: 'email',
            maxLength: 180,
            nullable: true,
            example: 'jean.dupont@example.com'
        )]
        #[Assert\Email(groups: ['create', 'update'])]
        #[Assert\Length(max: 180, groups: ['create', 'update'])]
        public ?string $email = null,

        #[OA\Property(
            description: 'Pièce d\'identité ou numéro d\'identification nationale',
            maxLength: 50,
            nullable: true,
            example: '123456789'
        )]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $nationalId = null,

        #[OA\Property(
            description: 'Adresse de résidence',
            maxLength: 255,
            nullable: true,
            example: '123 Avenue des Martyrs, Kinshasa'
        )]
        #[Assert\Length(max: 255, groups: ['create', 'update'])]
        public ?string $address = null,

        #[OA\Property(
            description: 'Notes complémentaires',
            nullable: true,
            example: 'Disponible pour affectations multiples'
        )]
        public ?string $notes = null,
    ) {
    }
}