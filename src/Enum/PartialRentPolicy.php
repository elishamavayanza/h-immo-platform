<?php

declare(strict_types=1);

namespace App\Enum;

enum PartialRentPolicy: string
{
    case CLAMP_REGULAR_DUE_DAY = 'clamp_regular_due_day';
    case COVERAGE_START = 'coverage_start';
    case COVERAGE_END = 'coverage_end';
}
