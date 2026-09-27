<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\RentRequest;
use App\Dto\Response\Rental\RentResponse;
use App\Entity\Rental\Rent;

/**
 * RentMapper
 *
 * Package : Rental Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Rent
 * et les objets DTO de requête et de réponse associés.
 * Le bail parent est positionné par le service appelant.
 */
final class RentMapper
{
    public function copyToEntity(RentRequest $request, Rent $rent): Rent
    {
        if ($request->period !== null) {
            $rent->setPeriod($request->period);
        }
        if ($request->dueDate !== null) {
            $rent->setDueDate($request->dueDate);
        }
        if ($request->amount !== null) {
            $rent->setAmount($request->amount);
        }
        if ($request->currency !== null) {
            $rent->setCurrency($request->currency);
        }
        if ($request->status !== null) {
            $rent->setStatus($request->status);
        }

        return $rent;
    }

    public function toResponse(Rent $rent): RentResponse
    {
        return new RentResponse(
            uuid: $rent->getUuid(),
            leaseUuid: $rent->getLease()->getUuid(),
            period: $rent->getPeriod(),
            dueDate: $rent->getDueDate(),
            amount: $rent->getAmount(),
            currency: $rent->getCurrency(),
            status: $rent->getStatus(),
            createdAt: $rent->getCreatedAt(),
            updatedAt: $rent->getUpdatedAt()
        );
    }
}
