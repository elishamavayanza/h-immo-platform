<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * RentStatus
 *
 * Statut de règlement d'une échéance de loyer (Rent).
 */
enum RentStatus: string
{
    case PENDING = 'pending';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
}
