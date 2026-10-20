<?php

declare(strict_types=1);

namespace App\Enum;

enum RentalRuleOverrideMode: string
{
    case INHERIT = 'inherit';
    case OVERRIDE = 'override';
}
