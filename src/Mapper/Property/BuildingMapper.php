<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\BuildingRequest;
use App\Dto\Response\Property\BuildingResponse;
use App\Entity\Property\Building;

/**
 * BuildingMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité Building
 * et ses DTOs de requête et de réponse associés.
 */
final readonly class BuildingMapper
{
    /**
     * Injecte le mapper de la parcelle parent pour la réponse imbriquée.
     * Permet la construction complète du DTO BuildingResponse.
     */
    public function __construct(
        private ParcelMapper $parcelMapper
    ) {
    }

    /**
     * Transforme une instance de l'entité Building en DTO de réponse BuildingResponse.
     * Extrait les informations publiques et la parcelle rattachée pour l'API.
     */
    public function toResponse(Building $building): BuildingResponse
    {
        return new BuildingResponse(
            uuid: $building->getUuid(),
            parcel: $this->parcelMapper->toResponse($building->getParcel()),
            reference: $building->getReference(),
            name: $building->getName(),
            type: $building->getType(),
            numberOfFloors: $building->getNumberOfFloors(),
            description: $building->getDescription(),
            createdAt: $building->getCreatedAt(),
            updatedAt: $building->getUpdatedAt()
        );
    }

    /**
     * Mappe les données d'un DTO BuildingRequest vers l'entité Building.
     * Effectue les mises à jour des propriétés modifiables du bâtiment.
     */
    public function copyToEntity(BuildingRequest $dto, Building $building): Building
    {
        if ($dto->reference !== null) {
            $building->setReference($dto->reference);
        }

        if ($dto->name !== null) {
            $building->setName($dto->name);
        }

        if ($dto->type !== null) {
            $building->setType($dto->type);
        }

        if ($dto->numberOfFloors !== null) {
            $building->setNumberOfFloors($dto->numberOfFloors);
        }

        if ($dto->description !== null) {
            $building->setDescription($dto->description);
        }

        return $building;
    }
}
