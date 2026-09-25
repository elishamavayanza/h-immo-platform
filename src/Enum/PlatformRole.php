<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * PlatformRole
 *
 * Rôle global au niveau de la plateforme Soft-IMMO (indépendant de
 * toute Organization). Actuellement limité au super-administrateur
 * technique de la plateforme.
 */
enum PlatformRole: string
{
    case SUPER_ADMIN = 'super_admin';
}
