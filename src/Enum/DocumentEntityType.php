<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentEntityType: string
{
    case LEASE = 'lease';
    case PAYMENT = 'payment';
    case EXPENSE = 'expense';
    case PARCEL = 'parcel';
    case BUILDING = 'building';
    case UNIT = 'unit';
}
