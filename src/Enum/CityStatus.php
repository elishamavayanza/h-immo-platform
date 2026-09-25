<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * CityStatus
 *
 * Statut d'activation d'une ville (City) au sein d'une Organization.
 */
enum CityStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
