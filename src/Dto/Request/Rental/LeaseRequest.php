<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\Currency;
use App\Enum\PartialRentPolicy;
use App\Enum\RentalPaymentMode;
use App\Enum\RentalRuleOverrideMode;
use App\Enum\RentProrationMethod;
use App\Enum\RentRoundingRule;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * LeaseRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'un contrat de bail (Lease).
 *
 * Le statut, la date de résiliation et son motif en sont absents, et c'est
 * délibéré : un bail naît DRAFT et change d'état par des transitions
 * explicites (activer, résilier, annuler), chacune soumise à son propre
 * contrôle d'accès. Les exposer en écriture permettait de repasser un bail
 * ACTIVE en DRAFT en ne changeant que sa date de fin.
 */
#[OA\Schema(
    title: 'LeaseRequest',
    description: 'Payload pour la création ou la modification d\'un contrat de location.'
)]
final readonly class LeaseRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public du locataire rattaché',
            format: 'uuid',
            example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $tenantUuid = null,

        #[OA\Property(
            description: 'UUID public du local / unité locative concernée',
            format: 'uuid',
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $unitUuid = null,

        #[OA\Property(description: 'UUID des espaces supplémentaires couverts par le bail', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))]
        #[Assert\All([new Assert\Uuid()], groups: ['create'])]
        #[Assert\Count(max: 20, groups: ['create'], maxMessage: 'Un bail ne peut pas couvrir plus de 20 espaces supplémentaires.')]
        public array $additionalUnitUuids = [],

        #[OA\Property(description: 'Cotitulaires et occupants non signataires avec leurs périodes', type: 'array', items: new OA\Items(type: 'object'))]
        #[Assert\Count(max: 50, groups: ['create', 'update'], maxMessage: 'Un bail ne peut pas contenir plus de 50 occupants supplémentaires.')]
        public array $occupants = [],

        #[OA\Property(
            description: 'Référence unique du contrat de bail',
            example: 'LEASE-2026-0042',
            maxLength: 50
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Date de prise d\'effet du bail',
            format: 'date',
            example: '2026-01-01'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?\DateTimeImmutable $startDate = null,

        #[OA\Property(
            description: 'Date d\'échéance du bail (null si bail à durée indéterminée)',
            format: 'date',
            example: '2026-12-31',
            nullable: true
        )]
        public ?\DateTimeImmutable $endDate = null,

        #[OA\Property(
            description: 'Montant du loyer mensuel convenu',
            example: '500.00'
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'Le loyer doit comporter au plus 2 décimales.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $monthlyRent = null,

        #[OA\Property(
            description: 'Montant de la garantie locative / caution',
            example: '1000.00',
            nullable: true
        )]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'La garantie doit comporter au plus 2 décimales.')]
        #[Assert\PositiveOrZero(groups: ['create', 'update'])]
        public ?string $depositAmount = null,

        #[OA\Property(
            description: 'Devise pour les transactions liées au contrat',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create', 'update'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Notes ou clauses particulières',
            example: 'Clause de révision annuelle de 5% incluse.',
            nullable: true,
            maxLength: 5000
        )]
        #[Assert\Length(max: 5000, groups: ['create', 'update'])]
        public ?string $notes = null,

        #[OA\Property(description: 'Mode explicite de résolution du paiement : héritage ou exception du bail.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $dueModeOverrideMode = null,
        #[OA\Property(description: 'Valeur du mode de paiement si le mode est override.', enum: RentalPaymentMode::class, nullable: true)]
        public ?RentalPaymentMode $dueModeOverrideValue = null,
        #[OA\Property(description: 'Mode explicite de résolution du jour d’exigibilité.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $dueDayOverrideMode = null,
        #[OA\Property(description: 'Jour d’exigibilité (1 à 31) si le mode est override.', minimum: 1, maximum: 31, nullable: true)]
        #[Assert\Range(min: 1, max: 31, groups: ['create', 'update'])]
        public ?int $dueDayOverrideValue = null,
        #[OA\Property(description: 'Mode explicite de résolution du premier mois partiel.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $firstPartialOverrideMode = null,
        #[OA\Property(description: 'Politique du premier mois partiel si le mode est override.', enum: PartialRentPolicy::class, nullable: true)]
        public ?PartialRentPolicy $firstPartialOverrideValue = null,
        #[OA\Property(description: 'Mode explicite de résolution du dernier mois partiel.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $lastPartialOverrideMode = null,
        #[OA\Property(description: 'Politique du dernier mois partiel si le mode est override.', enum: PartialRentPolicy::class, nullable: true)]
        public ?PartialRentPolicy $lastPartialOverrideValue = null,
        #[OA\Property(description: 'Mode explicite de résolution du prorata.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $prorationOverrideMode = null,
        #[OA\Property(description: 'Méthode de prorata si le mode est override.', enum: RentProrationMethod::class, nullable: true)]
        public ?RentProrationMethod $prorationOverrideValue = null,
        #[OA\Property(description: 'Mode explicite de résolution de l’arrondi.', enum: RentalRuleOverrideMode::class)]
        public ?RentalRuleOverrideMode $roundingOverrideMode = null,
        #[OA\Property(description: 'Règle d’arrondi si le mode est override.', enum: RentRoundingRule::class, nullable: true)]
        public ?RentRoundingRule $roundingOverrideValue = null,
    ) {
    }
}
