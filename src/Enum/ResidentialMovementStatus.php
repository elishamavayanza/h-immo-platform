<?php

declare(strict_types=1);

namespace App\Enum;

enum ResidentialMovementStatus: string
{
    case ANNOUNCED = 'announced';
    case PLANNED = 'planned';
    case POSTPONED = 'postponed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case RECORDED = 'recorded';
}
