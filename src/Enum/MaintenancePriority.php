<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenancePriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
}
