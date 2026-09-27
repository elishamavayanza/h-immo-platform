<?php

declare(strict_types=1);

namespace App\Mapper\Property;

use App\Dto\Request\Property\CityRequest;
use App\Dto\Response\Property\CityResponse;
use App\Entity\Property\City;
use App\Mapper\Identity\OrganizationMapper;

/**
 * CityMapper
 *
 * Package : Property Management — Service de mapping d'entité
 *
 * Assure la conversion bidirectionnelle entre l'entité City
 * et ses DTOs de requête et de réponse associés.
 */
final readonly class CityMapper
{
    /**
     * Injecte le mapper de l'organisation pour mapper les données d'appartenance.
     * Permet d'inclure les détails de l'organisation dans CityResponse.
     */
    public function __construct(
        private OrganizationMapper $organizationMapper
    ) {
    }

    /**
     * Transforme une instance de l'entité City en DTO de réponse CityResponse.
     * Convertit l'entité ville en structure de données prête pour l'exposition API.
     */
    public function toResponse(City $city): CityResponse
    {
        return new CityResponse(
            uuid: $city->getUuid(),
            organization: $this->organizationMapper->toResponse($city->getOrganization()),
            name: $city->getName(),
            code: $city->getCode(),
            province: $city->getProvince(),
            country: $city->getCountry(),
            status: $city->getStatus(),
            createdAt: $city->getCreatedAt(),
            updatedAt: $city->getUpdatedAt()
        );
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
