<?php

declare(strict_types=1);

namespace App\Enum;

enum PublicationCategory: string
{
    case FEATURE = 'feature';
    case IMPROVEMENT = 'improvement';
    case CORRECTION = 'correction';
    case MAINTENANCE = 'maintenance';
    case IMPORTANT = 'important';
}
