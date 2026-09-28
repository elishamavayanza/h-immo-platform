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

        // Le statut n'est jamais copié : `RentRequest` ne le contient pas.
        // Il se recalcule après coup (`Rent::syncStatus()`), car changer le
        // montant ou la date d'exigibilité change ce que doit être le
        // statut.

        return $rent;
    }

    /**
     * Délègue la projection Entité -> DTO à la fabrique statique du DTO de
     * réponse, qui est l'unique source de vérité du mapping en lecture.
     *
     * Elle existait déjà et était correcte, alors que ce mapper construisait
     * à la main un `RentResponse` en passant `uuid:` et `leaseUuid:` — des
     * noms que le DTO ne connaît pas (`id` et `leaseId`). Résultat : toute
     * lecture ou mise à jour d'un loyer renvoyait une erreur 500. La
     * duplication était la cause du défaut.
     */
    public function toResponse(Rent $rent): RentResponse
    {
        return RentResponse::fromEntity($rent);
    }
}
