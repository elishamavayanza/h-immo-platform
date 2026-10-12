<?php

declare(strict_types=1);

namespace App\Mapper\Rental;

use App\Dto\Request\Rental\TenantRequest;
use App\Dto\Response\Rental\TenantResponse;
use App\Entity\Rental\Tenant;
use App\Enum\TenantType;

final class TenantMapper
{
    public function copyToEntity(TenantRequest $request, Tenant $tenant): Tenant
    {
        if ($request->type !== null) {
            $tenant->setType($request->type);
        }

        // Les deux parties du nom sont envoyées ensemble lorsqu'elles changent.
        if ($request->type !== null) {
            if ($request->type === TenantType::INDIVIDUAL) {
                $fullName = trim(sprintf('%s %s', $request->firstName ?? '', $request->lastName ?? ''));
                $tenant->setFullName($fullName !== '' ? $fullName : null);
                $tenant->setCompanyName(null);
            } else {
                $tenant->setCompanyName($request->companyName);
                $tenant->setFullName(null);
            }
        }

        if ($request->phone !== null) {
            $tenant->setPhone($request->phone);
        }
        if ($request->email !== null) {
            $tenant->setEmail($request->email !== '' ? $request->email : null);
        }
        if ($request->address !== null) {
            $tenant->setAddress($request->address !== '' ? $request->address : null);
        }
        if ($request->notes !== null) {
            $tenant->setNotes($request->notes !== '' ? $request->notes : null);
        }

        return $tenant;
    }

    /**
     * Delegue la projection Entite -> DTO a la fabrique statique du DTO
     * de reponse, qui constitue l'unique source de verite du mapping
     * en lecture (aucune duplication de la liste des champs).
     */
    public function toResponse(Tenant $tenant): TenantResponse
    {
        return TenantResponse::fromEntity($tenant);
    }

}
