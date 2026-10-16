<?php

declare(strict_types=1);

namespace App\Enum;

enum ResidentialMovementType: string
{
    case MOVE_IN = 'move_in';
    case TRANSFER = 'transfer';
    case DEPARTURE_ANNOUNCED = 'departure_announced';
    case DEPARTURE_COMPLETED = 'departure_completed';
    case TEMPORARY_LEAVE = 'temporary_leave';
    case RETURN = 'return';
    case OCCUPANTS_CHANGED = 'occupants_changed';
    case CORRECTION = 'correction';
}
