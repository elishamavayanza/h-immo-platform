<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\LeaseRequest;
use App\Dto\Response\Rental\LeaseResponse;
use App\Entity\Rental\Lease;

/**
 * LeaseMapper
 *
 * Package : Rental Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Lease
 * et les objets DTO de requête et de réponse associés.
 * L'organisation, le locataire et l'unité sont positionnés par le
 * service appelant : le mapper ne fait que recopier les colonnes
 * scalaires du contrat.
 */
final class LeaseMapper
{
    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Lease $lease): LeaseResponse
    {
        return LeaseResponse::fromEntity($lease);
    }

    public function copyToEntity(LeaseRequest $dto, Lease $lease): Lease
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

        return $lease;
    }
}
