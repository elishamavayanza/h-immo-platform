<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentCategory: string
{
    case LEASE_CONTRACT = 'lease_contract';
    case PAYMENT_RECEIPT = 'payment_receipt';
    case INVOICE = 'invoice';
    case OTHER = 'other';
}
