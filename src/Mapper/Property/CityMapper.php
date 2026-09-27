<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\CityRequest;
use App\Dto\Response\Property\CityResponse;
use App\Entity\Property\City;

/**
 * CityMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité City
 * et ses DTOs de requête et de réponse associés.
 */
final class CityMapper
{
    /**
     * Injecte le mapper de l'organisation pour mapper les données d'appartenance.
     * Permet d'inclure les détails de l'organisation dans CityResponse.
     */
    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(City $city): CityResponse
    {
        return CityResponse::fromEntity($city);
    }


    /**
     * Mappe les données d'un DTO CityRequest vers l'entité City.
     * Met à jour les informations géographiques et administratives de la ville.
     */
    public function copyToEntity(CityRequest $dto, City $city): City
    {
        if ($dto->name !== null) {
            $city->setName($dto->name);
        }

        if ($dto->code !== null) {
            $city->setCode($dto->code);
        }

        if ($dto->province !== null) {
            $city->setProvince($dto->province);
        }

        if ($dto->country !== null) {
            $city->setCountry($dto->country);
        }

        $city->setStatus($dto->status);

        return $city;
    }
}
