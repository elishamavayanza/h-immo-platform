<?php

declare(strict_types=1);

namespace App\Dto\Request\Staff;

use App\Enum\Currency;
use App\Enum\WorkerRole;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * WorkerAssignmentRequest
 *
 * Package : Staff Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une affectation.
 *
 * Une affectation porte exactement UNE cible parmi parcel, building, unit.
 * La ville est obligatoire et déduit l'Organization.
 * Le worker doit appartenir à la même Organization.
 */
#[OA\Schema(
    title: 'WorkerAssignmentRequest',
    description: 'Payload pour la création ou la mise à jour d\'une affectation de travailleur.'
)]
final readonly class WorkerAssignmentRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public du travailleur',
            format: 'uuid',
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $workerUuid = null,

        #[OA\Property(
            description: 'UUID public de la ville d\'exercice (obligatoire)',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $cityUuid = null,

        #[OA\Property(
            description: 'UUID public de la parcelle (un parmi parcel/building/unit)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $parcelUuid = null,

        #[OA\Property(
            description: 'UUID public de l\'immeuble (un parmi parcel/building/unit)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $buildingUuid = null,

        #[OA\Property(
            description: 'UUID public de l\'unité (un parmi parcel/building/unit)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $unitUuid = null,

        #[OA\Property(
            description: 'Fonction exercée',
            type: 'string',
            example: 'gardien',
            enum: WorkerRole::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?string $role = null,

        #[OA\Property(
            description: 'Rémunération mensuelle de base',
            example: '500.00'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'Le salaire doit être un nombre positif avec au plus 2 décimales.')]
        public ?string $monthlySalary = null,

        #[OA\Property(
            description: 'Devise de la rémunération',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Date de début de l\'affectation',
            format: 'date',
            example: '2026-01-01'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?\DateTimeImmutable $startDate = null,

        #[OA\Property(
            description: 'Date de fin de l\'affectation (null si en cours)',
            format: 'date',
            nullable: true,
            example: '2026-12-31'
        )]
        public ?\DateTimeImmutable $endDate = null,

        #[OA\Property(
            description: 'Notes complémentaires',
            nullable: true,
            example: 'Affectation temporaire 3 mois'
        )]
        public ?string $notes = null,
    ) {
    }
}