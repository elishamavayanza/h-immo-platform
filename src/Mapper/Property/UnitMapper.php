<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\Property\UnitResponse;
use App\Entity\Property\Unit;

/**
 * UnitMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Unit
 * et les objets DTO de requête et de réponse associés.
 * Le bâtiment parent est positionné par le service appelant : le mapper
 * ne fait que recopier les colonnes scalaires de l'unité.
 */
final class UnitMapper
{
    public function copyToEntity(UnitRequest $request, Unit $unit): Unit
    {
        if ($request->reference !== null) {
            $unit->setReference($request->reference);
        }
        if ($request->type !== null) {
            $unit->setType($request->type);
        }
        if ($request->floor !== null) {
            $unit->setFloor($request->floor);
        }
        if ($request->surface !== null) {
            $unit->setSurface($request->surface);
        }
        if ($request->bedrooms !== null) {
            $unit->setBedrooms($request->bedrooms);
        }
        if ($request->rooms !== null) {
            $unit->setRooms($request->rooms);
        }
        if ($request->bathrooms !== null) {
            $unit->setBathrooms($request->bathrooms);
        }
        if ($request->monthlyRent !== null) {
            $unit->setMonthlyRent($request->monthlyRent);
        }
        if ($request->currency !== null) {
            $unit->setCurrency($request->currency);
        }
        if ($request->description !== null) {
            $unit->setDescription($request->description);
        }

        return $unit;
    }

    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Unit $unit): UnitResponse
    {
        return UnitResponse::fromEntity($unit);
    }

}
