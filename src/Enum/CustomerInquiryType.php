<?php

declare(strict_types=1);

namespace App\Enum;

enum CustomerInquiryType: string
{
    case INFORMATION = 'information';
    case VISIT = 'visit';
}
