<?php

declare(strict_types=1);

namespace App\Dto\Request\Rental;

use App\Enum\Currency;
use App\Enum\PaymentMethod;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PaymentRequest
 *
 * Package : Rental Management — DTO de requête
 *
 * Données entrantes pour l'enregistrement d'un règlement (Payment).
 */
#[OA\Schema(
    title: 'PaymentRequest',
    description: 'Payload pour l\'enregistrement ou l\'annotation d\'un paiement de loyer.'
)]
final readonly class PaymentRequest
{
    public function __construct(
        #[OA\Property(
            description: 'UUID public de l\'échéance de loyer réglée',
            format: 'uuid',
            example: 'f81d4fae-7dec-11d0-a765-00a0c91e6bf6'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Uuid(groups: ['create'])]
        public ?string $rentUuid = null,

        #[OA\Property(
            description: 'Montant versé (chiffres, max 2 décimales)',
            example: '500.00',
            pattern: '^\\d{1,10}(\\.\\d{1,2})?$'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        #[Assert\Regex(pattern: '/^\d{1,10}(\.\d{1,2})?$/', groups: ['create'], message: 'Le montant doit être un nombre positif avec au plus 2 décimales.')]
        public ?string $amount = null,

        #[OA\Property(
            description: 'Devise du paiement',
            type: 'string',
            example: 'USD',
            enum: Currency::class
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?Currency $currency = null,

        #[OA\Property(
            description: 'Taux de change utilisé (1 devise_originale = X devise_paiement). Obligatoire si currency diffère de celle de l\'échéance.',
            example: '2900.00000000',
            nullable: true
        )]
        public ?string $exchangeRate = null,

        #[OA\Property(
            description: 'Montant original dans la devise d\'origine (si conversion).',
            example: '100.00',
            nullable: true
        )]
        public ?string $originalAmount = null,

        #[OA\Property(
            description: 'Devise d\'origine du montant (si conversion).',
            type: 'string',
            example: 'USD',
            enum: Currency::class,
            nullable: true
        )]
        public ?Currency $originalCurrency = null,

        #[OA\Property(
            description: 'Date et heure de la transaction',
            format: 'date-time',
            example: '2026-09-05T14:30:00Z'
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?\DateTimeImmutable $paymentDate = null,

        #[OA\Property(
            description: 'Mode de paiement utilisé',
            type: 'string',
            example: 'bank_transfer',
            enum: PaymentMethod::class
        )]
        #[Assert\NotBlank(groups: ['create'])]
        public ?PaymentMethod $method = null,

        #[OA\Property(
            description: 'Référence bancaire, numéro de transaction ou id de virement',
            example: 'TXN-98421033',
            nullable: true,
            maxLength: 100
        )]
        #[Assert\Length(max: 100, groups: ['create', 'update'])]
        public ?string $reference = null,

        #[OA\Property(
            description: 'Numéro de reçu comptable papier ou physique',
            example: 'REC-2026-0901',
            nullable: true,
            maxLength: 50
        )]
        #[Assert\Length(max: 50, groups: ['create', 'update'])]
        public ?string $receiptNumber = null,

        #[OA\Property(
            description: 'Notes d\'accompagnement du paiement',
            example: 'Paiement effectué en deux tranches.',
            nullable: true
        )]
        public ?string $notes = null,
    ) {
    }
}
