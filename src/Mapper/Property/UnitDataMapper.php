<?php

declare(strict_types=1);

namespace App\DataMapper\Property;

use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\Property\UnitResponse;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;

final class UnitDataMapper
{
    public function toEntity(UnitRequest $request, Building $building): Unit
    {
        $unit = new Unit();
        $unit->setBuilding($building);

        return $this->updateEntity($unit, $request);
    }

    public function updateEntity(Unit $unit, UnitRequest $request): Unit
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

    public function toResponse(Unit $unit): UnitResponse
    {
        return new UnitResponse(
            uuid: $unit->getUuid(),
            buildingUuid: $unit->getBuilding()->getUuid(),
            buildingName: $unit->getBuilding()->getName(),
            reference: $unit->getReference(),
            type: $unit->getType(),
            floor: $unit->getFloor(),
            surface: $unit->getSurface(),
            bedrooms: $unit->getBedrooms(),
            rooms: $unit->getRooms(),
            bathrooms: $unit->getBathrooms(),
            monthlyRent: $unit->getMonthlyRent(),
            currency: $unit->getCurrency(),
            description: $unit->getDescription(),
            createdAt: $unit->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $unit->getUpdatedAt()?->format(\DateTimeInterface::ATOM)
        );
    }
}
