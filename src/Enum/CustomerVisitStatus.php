<?php

declare(strict_types=1);

namespace App\Enum;

enum CustomerVisitStatus: string
{
    case PROPOSED = 'proposed';
    case CONFIRMED = 'confirmed';
    case RESCHEDULED = 'rescheduled';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
