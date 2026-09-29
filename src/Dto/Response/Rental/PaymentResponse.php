<?php

declare(strict_types=1);

namespace App\Dto\Response\Rental;

use App\Entity\Rental\Payment;
use App\Enum\Currency;
use App\Enum\PaymentMethod;
use OpenApi\Attributes as OA;

/**
 * PaymentResponse
 *
 * Package : Rental Management — DTO de réponse
 */
#[OA\Schema(
    title: 'PaymentResponse',
    description: 'Représentation publique d\'un versement / encaissement de loyer.'
)]
final readonly class PaymentResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public du versement', format: 'uuid', example: '8f7e6d5c-4b3a-210f-9e8d-7c6b5a4f3e2d')]
        public string $id,

        #[OA\Property(description: 'UUID public de la quittance / loyer concerné', format: 'uuid', example: 'c3019a82-3ad4-4861-a53c-1123a1a3b110')]
        public string $rentId,

        #[OA\Property(description: 'UUID public de l\'agent ayant enregistré le paiement', format: 'uuid', example: 'd5e6f7a8-b9c0-1d2e-3f4a-5b6c7d8e9f0a')]
        public string $createdById,

        #[OA\Property(description: 'Montant versé', example: '450.00')]
        public string $amount,

        #[OA\Property(description: 'Devise monétaire de l\'encaissement', type: 'string', example: 'USD', enum: Currency::class)]
        public Currency $currency,

        #[OA\Property(description: 'Taux de change utilisé (1 devise_originale = X devise_paiement). Null si pas de conversion.', example: '2900.00000000', nullable: true)]
        public ?string $exchangeRate,

        #[OA\Property(description: 'Montant original dans la devise d\'origine. Null si pas de conversion.', example: '100.00', nullable: true)]
        public ?string $originalAmount,

        #[OA\Property(description: 'Devise d\'origine du montant. Null si pas de conversion.', type: 'string', example: 'USD', enum: Currency::class, nullable: true)]
        public ?Currency $originalCurrency,

        #[OA\Property(description: 'Date effectuation du paiement', format: 'date-time', example: '2026-03-02T10:15:00Z')]
        public \DateTimeImmutable $paymentDate,

        #[OA\Property(description: 'Mode de règlement utilisé', type: 'string', example: 'mobile_money', enum: PaymentMethod::class)]
        public PaymentMethod $method,

        #[OA\Property(description: 'Référence de la transaction (ex: ID M-Pesa, n° de chèque)', example: 'MP260302.1015.C01', nullable: true)]
        public ?string $reference,

        #[OA\Property(description: 'Numéro officiel de reçu généré', example: 'REC-2026-00124', nullable: true)]
        public ?string $receiptNumber,

        #[OA\Property(description: 'Remarques complémentaires', example: 'Versement effectué via Airtel Money', nullable: true)]
        public ?string $notes,

        #[OA\Property(description: 'Horodatage de création de la saisie', format: 'date-time', example: '2026-03-02T10:16:00Z')]
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(Payment $payment): self
    {
        return new self(
            id: (string) $payment->getUuid(),
            rentId: (string) $payment->getRent()->getUuid(),
            createdById: (string) $payment->getCreatedBy()->getUuid(),
            amount: $payment->getAmount(),
            currency: $payment->getCurrency(),
            exchangeRate: $payment->getExchangeRate(),
            originalAmount: $payment->getOriginalAmount(),
            originalCurrency: $payment->getOriginalCurrency(),
            paymentDate: $payment->getPaymentDate(),
            method: $payment->getMethod(),
            reference: $payment->getReference(),
            receiptNumber: $payment->getReceiptNumber(),
            notes: $payment->getNotes(),
            createdAt: $payment->getCreatedAt(),
        );
    }
}
