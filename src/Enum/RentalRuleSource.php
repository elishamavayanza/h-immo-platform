<?php

declare(strict_types=1);

namespace App\Enum;

enum RentalRuleSource: string
{
    case LEASE_OVERRIDE = 'lease_override';
    case ORGANIZATION_VERSION = 'organization_version';
    case ORGANIZATION_CANDIDATE = 'organization_candidate';
    case SOFT_IMMO_DEFAULT = 'soft_immo_default';
}
