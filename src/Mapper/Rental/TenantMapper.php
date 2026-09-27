<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\TenantRequest;
use App\Dto\Response\Rental\TenantResponse;
use App\Entity\Identity\Organization;
use App\Entity\Rental\Tenant;
use App\Enum\TenantType;

final class TenantMapper
{
    public function toEntity(
        TenantRequest $request,
        Organization $organization,
        ?Tenant $tenant = null
    ): Tenant {
        $tenant ??= new Tenant();

        $tenant->setOrganization($organization);

        if ($request->type !== null) {
            $tenant->setType($request->type);
        }

        // Assemblage du nom complet si c'est une personne physique
        if ($request->type === TenantType::INDIVIDUAL) {
            $fullName = trim(sprintf('%s %s', $request->firstName ?? '', $request->lastName ?? ''));
            $tenant->setFullName($fullName !== '' ? $fullName : null);
            $tenant->setCompanyName(null);
        } else {
            $tenant->setCompanyName($request->companyName);
            $tenant->setFullName(null);
        }

        if ($request->phone !== null) {
            $tenant->setPhone($request->phone);
        }
        $tenant->setEmail($request->email);
        $tenant->setAddress($request->address);
        $tenant->setNotes($request->notes);

        return $tenant;
    }

    public function toResponse(Tenant $tenant): TenantResponse
    {
        return new TenantResponse(
            uuid: $tenant->getUuid(),
            organizationUuid: $tenant->getOrganization()->getUuid(),
            type: $tenant->getType(),
            fullName: $tenant->getFullName(),
            companyName: $tenant->getCompanyName(),
            phone: $tenant->getPhone(),
            email: $tenant->getEmail(),
            address: $tenant->getAddress(),
            notes: $tenant->getNotes(),
            createdAt: $tenant->getCreatedAt(),
            updatedAt: $tenant->getUpdatedAt()
        );
    }
}
