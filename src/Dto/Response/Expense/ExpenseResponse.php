<?php

declare(strict_types=1);

namespace App\Dto\Response\Expense;

use App\Entity\Expense\Expense;
use App\Enum\Currency;
use App\Enum\ExpenseCategory;
use App\Enum\PaymentMethod;
use OpenApi\Attributes as OA;

/**
 * ExpenseResponse
 *
 * Package : Expense Management — DTO de réponse
 */
#[OA\Schema(
    title: 'ExpenseResponse',
    description: 'Représentation publique d\'une dépense.'
)]
final readonly class ExpenseResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de la dépense', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation', format: 'uuid', example: 'e3b0c442-98fc-4282-9a3b-2b0d7b3dcb6d')]
        public string $organizationId,

        #[OA\Property(description: 'UUID public de la ville', format: 'uuid', example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6')]
        public string $cityId,

        #[OA\Property(description: 'UUID public de la parcelle', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $parcelId,

        #[OA\Property(description: 'UUID public de l\'immeuble', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $buildingId,

        #[OA\Property(description: 'UUID public de l\'unité', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $unitId,

        #[OA\Property(description: 'UUID public du travailleur', format: 'uuid', nullable: true, example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public ?string $workerId,

        #[OA\Property(description: 'UUID public de l\'utilisateur créateur', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')]
        public string $createdById,

        #[OA\Property(description: 'Catégorie de la dépense', type: 'string', example: 'maintenance', enum: ExpenseCategory::class)]
        public ExpenseCategory $category,

        #[OA\Property(description: 'Montant de la dépense', example: '1250.00')]
        public string $amount,

        #[OA\Property(description: 'Devise', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Date de la dépense', format: 'date', example: '2026-03-15')]
        public \DateTimeImmutable $expenseDate,

        #[OA\Property(description: 'Début de période', format: 'date', nullable: true, example: '2026-01-01')]
        public ?\DateTimeImmutable $periodStart,

        #[OA\Property(description: 'Fin de période', format: 'date', nullable: true, example: '2026-12-31')]
        public ?\DateTimeImmutable $periodEnd,

        #[OA\Property(description: 'Mode de règlement', type: 'string', nullable: true, example: 'bank_transfer', enum: PaymentMethod::class)]
        public ?PaymentMethod $method,

        #[OA\Property(description: 'Tiers payeur', nullable: true, maxLength: 200, example: 'Service des impôts fonciers')]
        public ?string $supplier,

        #[OA\Property(description: 'Référence interne/extérieure', nullable: true, maxLength: 100, example: 'TF-2026-0042')]
        public ?string $reference,

        #[OA\Property(description: 'Numéro de pièce justificative', nullable: true, maxLength: 50, example: 'FAC-2026-00123')]
        public ?string $receiptNumber,

        #[OA\Property(description: 'Notes', nullable: true, example: 'Taxe foncière annuelle')]
        public ?string $notes,

        #[OA\Property(description: 'Horodatage de création', format: 'date-time', example: '2026-03-15T10:30:00Z')]
        public \DateTimeImmutable $createdAt,

        #[OA\Property(description: 'Horodatage de dernière mise à jour', format: 'date-time', example: '2026-03-15T14:20:00Z')]
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function fromEntity(Expense $expense): self
    {
        return new self(
            id: (string) $expense->getUuid(),
            organizationId: (string) $expense->getOrganization()->getUuid(),
            cityId: (string) $expense->getCity()->getUuid(),
            parcelId: $expense->getParcel() ? (string) $expense->getParcel()->getUuid() : null,
            buildingId: $expense->getBuilding() ? (string) $expense->getBuilding()->getUuid() : null,
            unitId: $expense->getUnit() ? (string) $expense->getUnit()->getUuid() : null,
            workerId: $expense->getWorker() ? (string) $expense->getWorker()->getUuid() : null,
            createdById: (string) $expense->getCreatedBy()->getUuid(),
            category: $expense->getCategory(),
            amount: $expense->getAmount(),
            currency: $expense->getCurrency(),
            expenseDate: $expense->getExpenseDate(),
            periodStart: $expense->getPeriodStart(),
            periodEnd: $expense->getPeriodEnd(),
            method: $expense->getMethod(),
            supplier: $expense->getSupplier(),
            reference: $expense->getReference(),
            receiptNumber: $expense->getReceiptNumber(),
            notes: $expense->getNotes(),
            createdAt: $expense->getCreatedAt(),
            updatedAt: $expense->getUpdatedAt(),
        );
    }
}