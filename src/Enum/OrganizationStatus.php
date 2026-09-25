<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * OrganizationStatus
 *
 * Statut du cycle de vie d'une Organization cliente sur la plateforme.
 */
enum OrganizationStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case INACTIVE = 'inactive';
}
