<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenanceStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
