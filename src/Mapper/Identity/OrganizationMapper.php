<?php

declare(strict_types=1);

namespace App\Mapper\Identity;

use App\Dto\Request\Identity\OrganizationRequest;
use App\Dto\Response\Identity\OrganizationResponse;
use App\Entity\Identity\Organization;

/**
 * OrganizationMapper
 *
 * Package : Identity & Access — Service de mapping d'entité
 *
 * Assure la transformation bidirectionnelle entre l'entité Organization
 * et les objet DTOs de requête et de réponse associés.
 */
final class OrganizationMapper
{
    /**
     * Transforme une instance de l'entité Organization en DTO de réponse.
     * Reçoit l'entité source et produit la charge utile destinée aux réponses API.
     */
    public function toResponse(Organization $organization): OrganizationResponse
    {
        return new OrganizationResponse(
            uuid: $organization->getUuid(),
            name: $organization->getName(),
            code: $organization->getCode(),
            logo: $organization->getLogo(),
            email: $organization->getEmail(),
            phone: $organization->getPhone(),
            address: $organization->getAddress(),
            city: $organization->getCity(),
            country: $organization->getCountry(),
            status: $organization->getStatus(),
            createdAt: $organization->getCreatedAt(),
            updatedAt: $organization->getUpdatedAt()
        );
    }

    /**
     * Mappe les données d'un DTO OrganizationRequest vers une entité Organization.
     * Hydrate l'instance fournie (existante ou nouvelle) à partir du payload validé.
     */
    public function copyToEntity(OrganizationRequest $dto, Organization $organization): Organization
    {
        if ($dto->name !== null) {
            $organization->setName($dto->name);
        }
        if ($dto->code !== null) {
            $organization->setCode($dto->code);
        }
        if ($dto->logo !== null) {
            $organization->setLogo($dto->logo);
        }
        if ($dto->email !== null) {
            $organization->setEmail($dto->email);
        }
        if ($dto->phone !== null) {
            $organization->setPhone($dto->phone);
        }
        if ($dto->address !== null) {
            $organization->setAddress($dto->address);
        }
        if ($dto->city !== null) {
            $organization->setCity($dto->city);
        }
        if ($dto->country !== null) {
            $organization->setCountry($dto->country);
        }
        if ($dto->status !== null) {
            $organization->setStatus($dto->status);
        }

        return $organization;
    }
}
