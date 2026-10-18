<?php

declare(strict_types=1);

namespace App\Dto\Request\Expense;

use App\Enum\Currency;
use App\Enum\ExpenseCategory;
use App\Enum\PaymentMethod;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ExpenseRequest
 *
 * Package : Expense Management — DTO de requête
 *
 * Données entrantes pour la création/mise à jour d'une dépense.
 *
 * L'Organization est déduite de la ville (jamais fournie par le client).
 * Au plus une cible parmi parcel, building, unit, et elle doit appartenir
 * à la ville fournie. `worker` est requis si et seulement si la catégorie
 * est SALARY, et doit appartenir à la même Organization.
 */
#[OA\Schema(
    title: 'ExpenseRequest',
    description: 'Payload pour la création ou la mise à jour d\'une dépense.'
)]
final readonly class ExpenseRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de la ville concernée (obligatoire)',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $cityUuid = null,

        #[OA\Property(
            description: 'UUID public de la parcelle (un parmi parcel/building/unit au plus)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $parcelUuid = null,

        #[OA\Property(
            description: 'UUID public de l\'immeuble (un parmi parcel/building/unit au plus)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $buildingUuid = null,

        #[OA\Property(
            description: 'UUID public de l\'unité (un parmi parcel/building/unit au plus)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $unitUuid = null,

        #[OA\Property(
            description: 'UUID public du travailleur (requis pour catégorie SALARY)',
            format: 'uuid',
            nullable: true,
            example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110'
        )]
        #[Assert\Uuid(groups: ['create', 'update'])]
        public ?string $workerUuid = null,

        #[OA\Property(
            description: 'Catégorie de la dépense',
            type: 'string',
            example: 'maintenance',
            enum: ExpenseCategory::class
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Choice(choices: ['salary', 'tax', 'maintenance', 'utility', 'insurance', 'management_fee', 'supply', 'cleaning', 'security', 'public_service', 'notary_fee', 'other'], groups: ['create', 'update'])]
        public ?string $category = null,

        #[OA\Property(
            description: 'Montant de la dépense (strictement positif)',
            example: '1250.00'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'Le montant doit être un nombre positif avec au plus 2 décimales.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $amount = null,

        #[OA\Property(
            description: 'Devise de la dépense',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Taux de change utilisé (1 devise_originale = X devise_dépense). Obligatoire si currency diffère de la devise de référence.',
            example: '2900.00000000',
            nullable: true
        )]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,8})?$/', groups: ['create', 'update'], message: 'Le taux doit comporter au plus 8 décimales.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $exchangeRate = null,

        #[OA\Property(
            description: 'Montant original dans la devise d\'origine (si conversion).',
            example: '100.00',
            nullable: true
        )]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create', 'update'], message: 'Le montant d’origine doit comporter au plus 2 décimales.')]
        #[Assert\Positive(groups: ['create', 'update'])]
        public ?string $originalAmount = null,

        #[OA\Property(
            description: 'Devise d\'origine de la dépense (si conversion).',
            type: 'string',
            example: 'USD',
            enum: Currency::class,
            nullable: true
        )]
        public ?Currency $originalCurrency = null,

        #[OA\Property(
            description: 'Date à laquelle la dépense a été engagée ou réglée',
            format: 'date',
            example: '2026-03-15'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?\DateTimeImmutable $expenseDate = null,

        #[OA\Property(
            description: 'Début de la période couverte (si dépense périodique)',
            format: 'date',
            nullable: true,
            example: '2026-01-01'
        )]
        public ?\DateTimeImmutable $periodStart = null,

        #[OA\Property(
            description: 'Fin de la période couverte (si dépense périodique)',
            format: 'date',
            nullable: true,
            example: '2026-12-31'
        )]
        public ?\DateTimeImmutable $periodEnd = null,

        #[OA\Property(
            description: 'Mode de règlement',
            type: 'string',
            example: 'bank_transfer',
            enum: PaymentMethod::class,
            nullable: true
        )]
        #[Assert\Choice(choices: ['cash', 'bank_transfer', 'mobile_money', 'card', 'other'], groups: ['create', 'update'])]
        public ?string $method = null,

        #[OA\Property(
            description: 'Tiers payeur : administration fiscale, fournisseur, etc.',
            nullable: true,
            maxLength: 200,
            example: 'Service des impôts fonciers'
        )]
        #[Assert\Length(max: 200, groups: ['create', 'update'])]
        public ?string $supplier = null,

        #[OA\Property(
            description: 'Référence interne ou externe de la dépense',
            nullable: true,
            maxLength: 100,
            example: 'TF-2026-0042'
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Numéro de la pièce justificative',
            nullable: true,
            maxLength: 50,
            example: 'FAC-2026-00123'
        )]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $receiptNumber = null,

        #[OA\Property(
            description: 'Description ou notes complémentaires',
            nullable: true,
            maxLength: 5000,
            example: 'Taxe foncière annuelle parcelle PA-1'
        )]
        #[Assert\Length(max: 5000, groups: ['create', 'update'])]
        public ?string $notes = null,
    ) {
    }
}
