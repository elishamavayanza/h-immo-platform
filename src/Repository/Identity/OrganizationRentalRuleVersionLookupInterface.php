<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationRentalRuleVersion;

/** Accès en lecture aux versions publiées, isolé pour permettre les tests de résolution sans base. */
interface OrganizationRentalRuleVersionLookupInterface
{
    public function findApplicableForPeriod(
        Organization $organization,
        \DateTimeImmutable $periodStart,
    ): ?OrganizationRentalRuleVersion;

    /** @return list<OrganizationRentalRuleVersion> */
    public function findAllForOrganization(Organization $organization): array;
}
