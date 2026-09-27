<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\PaymentRequest;
use App\Dto\Response\Rental\PaymentResponse;
use App\Entity\Rental\Payment;

final class PaymentDataMapper
{
    public function toResponse(Payment $payment): PaymentResponse
    {
        return new PaymentResponse(
            uuid: $payment->getUuid()->toString(),
            rentUuid: $payment->getRent()->getUuid()->toString(),
            createdByUuid: $payment->getCreatedBy()->getUuid()->toString(),
            amount: $payment->getAmount(),
            currency: $payment->getCurrency(),
            paymentDate: $payment->getPaymentDate(),
            method: $payment->getMethod(),
            reference: $payment->getReference(),
            receiptNumber: $payment->getReceiptNumber(),
            notes: $payment->getNotes(),
            createdAt: $payment->getCreatedAt()
        );
    }

    public function mapRequestToEntity(PaymentRequest $dto, Payment $payment): void
    {
        if ($dto->amount !== null) {
            $payment->setAmount($dto->amount);
        }
        if ($dto->currency !== null) {
            $payment->setCurrency($dto->currency);
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
    }
}
