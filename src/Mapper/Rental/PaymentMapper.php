<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\PaymentRequest;
use App\Dto\Response\Rental\PaymentResponse;
use App\Entity\Rental\Payment;

final class PaymentMapper
{
    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Payment $payment): PaymentResponse
    {
        return PaymentResponse::fromEntity($payment);
    }

    public function copyToEntity(PaymentRequest $dto, Payment $payment): Payment
    {
        if ($dto->amount !== null) {
            $payment->setAmount($dto->amount);
        }
        if ($dto->currency !== null) {
            $payment->setCurrency($dto->currency);
        }
        if ($dto->exchangeRate !== null) {
            $payment->setExchangeRate($dto->exchangeRate);
        }
        if ($dto->originalAmount !== null) {
            $payment->setOriginalAmount($dto->originalAmount);
        }
        if ($dto->originalCurrency !== null) {
            $payment->setOriginalCurrency($dto->originalCurrency);
        }
        if ($dto->paymentDate !== null) {
            $payment->setPaymentDate($dto->paymentDate);
        }
        if ($dto->method !== null) {
            $payment->setMethod($dto->method);
        }
        if ($dto->reference !== null) {
            $payment->setReference($dto->reference);
        }
        if ($dto->receiptNumber !== null) {
            $payment->setReceiptNumber($dto->receiptNumber);
        }
        if ($dto->notes !== null) {
            $payment->setNotes($dto->notes);
        }

        return $payment;
    }
}
