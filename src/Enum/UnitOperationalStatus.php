<?php

declare(strict_types=1);

namespace App\Enum;

/** État opérationnel manuel d’une unité; l’occupation reste dérivée des baux. */
enum UnitOperationalStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case PENDING_HANDOVER = 'pending_handover';
    case UNDER_WORKS = 'under_works';
    case TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
    case RELEASING = 'releasing';
    case NOT_FOR_RENT = 'not_for_rent';
}
