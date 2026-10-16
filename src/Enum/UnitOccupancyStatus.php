<?php

declare(strict_types=1);

namespace App\Enum;

/** État d’occupation dérivé des baux actifs et de la hiérarchie des espaces. */
enum UnitOccupancyStatus: string
{
    case VACANT = 'vacant';
    case OCCUPIED = 'occupied';
    case PARTIALLY_OCCUPIED = 'partially_occupied';
}
