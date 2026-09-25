<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * BuildingType
 *
 * Nature d'un bâtiment (Building) rattaché à une parcelle.
 */
enum BuildingType: string
{
    case APARTMENT = 'apartment';
    case COMMERCIAL = 'commercial';
    case OFFICE = 'office';
    case RESTAURANT = 'restaurant';
    case MIXED = 'mixed';
}
