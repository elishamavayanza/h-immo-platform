<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * PaymentMethod
 *
 * Moyen de paiement utilisé pour régler une échéance de loyer.
 */
enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case MOBILE_MONEY = 'mobile_money';
    case CARD = 'card';
    case OTHER = 'other';
}
