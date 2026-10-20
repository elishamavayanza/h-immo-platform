<?php

declare(strict_types=1);

namespace App\Enum;

enum RentalPaymentMode: string
{
    case IN_ADVANCE = 'in_advance';
    case IN_ARREARS = 'in_arrears';
}
