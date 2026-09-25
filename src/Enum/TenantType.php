<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * TenantType
 *
 * Nature juridique d'un locataire (Tenant) : personne physique
 * ou personne morale (entreprise).
 */
enum TenantType: string
{
    case INDIVIDUAL = 'individual';
    case COMPANY = 'company';
}
