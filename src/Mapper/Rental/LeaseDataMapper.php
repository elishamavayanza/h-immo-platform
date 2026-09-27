<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\LeaseRequest;
use App\Dto\Response\Rental\LeaseResponse;
use App\Entity\Rental\Lease;

final class LeaseDataMapper
{
    public function toResponse(Lease $lease): LeaseResponse
    {
        return new LeaseResponse(
            uuid: $lease->getUuid()->toString(),
            tenantUuid: $lease->getTenant()->getUuid()->toString(),
            unitUuid: $lease->getUnit()->getUuid()->toString(),
            reference: $lease->getReference(),
            startDate: $lease->getStartDate(),
            endDate: $lease->getEndDate(),
            monthlyRent: $lease->getMonthlyRent(),
            depositAmount: $lease->getDepositAmount(),
            currency: $lease->getCurrency(),
            status: $lease->getStatus(),
            terminationDate: $lease->getTerminationDate(),
            terminationReason: $lease->getTerminationReason(),
            notes: $lease->getNotes(),
            createdAt: $lease->getCreatedAt(),
            updatedAt: $lease->getUpdatedAt()
        );
    }

    public function mapRequestToEntity(LeaseRequest $dto, Lease $lease): void
    {
        if ($dto->reference !== null) {
            $lease->setReference($dto->reference);
        }
        if ($dto->startDate !== null) {
            $lease->setStartDate($dto->startDate);
        }
        if ($dto->endDate !== null) {
            $lease->setEndDate($dto->endDate);
        }
        if ($dto->monthlyRent !== null) {
            $lease->setMonthlyRent($dto->monthlyRent);
        }
        if ($dto->depositAmount !== null) {
            $lease->setDepositAmount($dto->depositAmount);
        }
        if ($dto->currency !== null) {
            $lease->setCurrency($dto->currency);
        }
        if ($dto->status !== null) {
            $lease->setStatus($dto->status);
        }
        if ($dto->terminationDate !== null) {
            $lease->setTerminationDate($dto->terminationDate);
        }
        if ($dto->terminationReason !== null) {
            $lease->setTerminationReason($dto->terminationReason);
        }
        if ($dto->notes !== null) {
            $lease->setNotes($dto->notes);
        }
    }
}
