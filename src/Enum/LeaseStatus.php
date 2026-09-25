<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * LeaseStatus
 *
 * Statut du cycle de vie d'un contrat de bail (Lease).
 * Règle métier associée : une Unit ne peut avoir qu'un seul Lease
 * au statut ACTIVE à la fois (voir contrainte métier dédiée).
 */
enum LeaseStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case TERMINATED = 'terminated';
    case CANCELLED = 'cancelled';
}
